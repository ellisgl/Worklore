<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Company\Application\ProjectNotFound;
use App\Company\ProjectRepository;
use App\Journal\JournalEntry;
use App\Journal\JournalEntryRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use App\Taxonomy\Application\TagLookup;
use App\Taxonomy\Application\TagNotFound;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Writes the entry once its project and tags are known to be the owner's.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateJournalEntryHandler
{
    public function __construct(
        private JournalEntryRepository $entries,
        private ProjectRepository $projects,
        private TagLookup $tags,
        private OwnerReferences $owners,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the title or entry is blank, or the title is too long
     * @throws ORMException
     * @throws ProjectNotFound
     * @throws TagNotFound
     */
    public function __invoke(CreateJournalEntry $command): void
    {
        $project = $this->projects->findForOwner($command->projectId, $command->ownerId)
            ?? throw new ProjectNotFound();

        $this->entries->save(JournalEntry::write(
            $command->entryId,
            $project->id(),
            $command->ownerId,
            $command->title,
            $command->entry,
            $this->tags->idsOwnedBy($this->owners->get($command->ownerId), $command->tagIds),
            $this->clock->now()->setTimezone(new DateTimeZone('UTC')),
        ));
    }
}
