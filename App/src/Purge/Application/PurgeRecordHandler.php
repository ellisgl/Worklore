<?php

declare(strict_types=1);

namespace App\Purge\Application;

use App\Company\ProjectId;
use App\Company\ProjectRepository;
use App\Journal\JournalEntryId;
use App\Journal\JournalEntryRepository;
use App\Purge\PurgePlan;
use App\Purge\PurgePlanner;
use App\Purge\PurgeReport;
use App\Purge\PurgeTarget;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\EventSourcing\EventStore;
use App\Shared\EventSourcing\Projections;
use App\Shared\Security\Impersonation;
use App\Shared\UserId;
use App\Taxonomy\TagId;
use Doctrine\DBAL\Exception;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * True deletion, in one transaction:
 *
 * 1. Records that live on but carry a purged tag are retagged without it,
 *    as ordinary events, so no surviving history points at the tag.
 * 2. Every purged record's events are erased from the event store.
 * 3. The projectors delete their rows.
 * 4. After the commit, the purged journal entries' files are deleted.
 *
 * Only ids and counts are logged, never content.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[WithMonologChannel('domain')]
final readonly class PurgeRecordHandler
{
    public function __construct(
        private PurgePlanner $planner,
        private ProjectRepository $projects,
        private JournalEntryRepository $entries,
        private EventStore $events,
        private Projections $projections,
        private Impersonation $impersonation,
        // Named autowiring: $eventBus is the event.bus service.
        private MessageBusInterface $eventBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws RecordNotFound
     */
    public function __invoke(PurgeRecord $command): PurgeReport
    {
        $plan = $this->planner->plan($command->ownerId, $command->target, $command->id)
            ?? throw new RecordNotFound();

        $this->untag($command->ownerId, $plan);
        $erased = $this->events->erase($command->ownerId, $plan->allIds());
        foreach (PurgeTarget::cases() as $target) {
            $this->projections->forget($target->streamType(), $plan->ids($target));
        }
        if ([] !== $plan->mediaIds) {
            $this->eventBus->dispatch(new MediaPurged($command->ownerId, $plan->mediaIds), [new DispatchAfterCurrentBusStamp()]);
        }

        $report = $plan->report(dryRun: false);
        $this->logger->notice('Records purged', [
            'impersonator_id' => $this->impersonation->impersonatorId()?->toRfc4122(),
            'owner_id' => $command->ownerId->toRfc4122(),
            'target' => $command->target->value,
            'id' => $command->id,
            'purged' => $report->purged,
            'events' => $erased,
            'record_ids' => $plan->allIds(),
        ]);

        return $report;
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    private function untag(UserId $ownerId, PurgePlan $plan): void
    {
        foreach ($plan->untagProjects as $id) {
            $project = $this->projects->findForOwner(ProjectId::fromString($id), $ownerId);
            if (null !== $project) {
                $project->retag(self::without($project->tagIds, $plan));
                $this->projects->save($project);
            }
        }
        foreach ($plan->untagEntries as $id) {
            $entry = $this->entries->findForOwner(JournalEntryId::fromString($id), $ownerId);
            if (null !== $entry) {
                $entry->retag(self::without($entry->tagIds, $plan));
                $this->entries->save($entry);
            }
        }
    }

    /**
     * @param list<TagId> $tagIds
     *
     * @return list<TagId>
     */
    private static function without(array $tagIds, PurgePlan $plan): array
    {
        $kept = [];
        foreach ($tagIds as $tagId) {
            if (!$plan->purgesTag($tagId->toRfc4122())) {
                $kept[] = $tagId;
            }
        }

        return $kept;
    }
}
