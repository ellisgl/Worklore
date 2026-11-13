<?php

declare(strict_types=1);

namespace App\Journal\Controller;

use App\Company\Application\ProjectNotFound;
use App\Company\ProjectId;
use App\Company\Query\ProjectQueries;
use App\Journal\Application\ArchiveJournalEntry;
use App\Journal\Application\CreateJournalEntry;
use App\Journal\Application\EditJournalEntry;
use App\Journal\Application\JournalEntryNotFound;
use App\Journal\Application\UnarchiveJournalEntry;
use App\Journal\JournalEntryId;
use App\Journal\Query\JournalEntryQueries;
use App\Shared\Http\PageRequest;
use App\Shared\InvalidValueException;
use App\Shared\Owner;
use App\Taxonomy\TagId;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

use function array_map;

/**
 * Journal entries: listed and written under their project, read and changed
 * by id. Reads call the query classes; writes dispatch a command.
 */
#[Route('/api', name: 'api_journal_entries_')]
final class JournalEntryController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
        private readonly JournalEntryQueries $entries,
        private readonly ProjectQueries $projects,
    ) {
    }

    /**
     * GET /api/projects/{projectId}/journal-entries[?include_archived=1][&page=1&per_page=50],
     * newest first.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     * @throws ProjectNotFound
     */
    #[Route('/projects/{projectId}/journal-entries', name: 'list', requirements: ['projectId' => Requirement::UUID], methods: ['GET'])]
    public function list(
        #[CurrentUser] Owner $owner,
        string $projectId,
        #[MapQueryParameter(name: 'include_archived')] bool $includeArchived = false,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        $project = ProjectId::fromString($projectId);
        if (!$this->projects->existsForOwner($owner->ownerId(), $project)) {
            throw new ProjectNotFound();
        }

        return $this->json($this->entries->listForProject($owner->ownerId(), $project, $includeArchived, $paging->toPage()));
    }

    /**
     * POST /api/projects/{projectId}/journal-entries
     * {"title": "...", "entry": "...", "tag_ids": ["..."]} → 201 {"id": "..."}
     *
     * @throws ExceptionInterface
     */
    #[Route('/projects/{projectId}/journal-entries', name: 'create', requirements: ['projectId' => Requirement::UUID], methods: ['POST'])]
    public function create(
        #[CurrentUser] Owner $owner,
        string $projectId,
        #[MapRequestPayload] CreateJournalEntryRequest $payload,
    ): JsonResponse {
        $id = JournalEntryId::generate();
        $this->commandBus->dispatch(new CreateJournalEntry(
            $id,
            ProjectId::fromString($projectId),
            $owner->ownerId(),
            $payload->title,
            $payload->entry,
            self::tagIds($payload->tagIds),
        ));

        return $this->json(['id' => $id->toRfc4122()], Response::HTTP_CREATED);
    }

    /**
     * GET /api/journal-entries/{id}: one of the caller's entries, archived or not.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws JournalEntryNotFound
     */
    #[Route('/journal-entries/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(#[CurrentUser] Owner $owner, string $id): JsonResponse
    {
        return $this->json($this->entries->findForOwner($owner->ownerId(), JournalEntryId::fromString($id)) ?? throw new JournalEntryNotFound());
    }

    /**
     * PATCH /api/journal-entries/{id} {"title": "...", "entry": "...", "tag_ids": [...]} → 204.
     * Only the fields sent are changed; tag_ids replaces the tags.
     *
     * @throws ExceptionInterface
     */
    #[Route('/journal-entries/{id}', name: 'edit', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function edit(
        #[CurrentUser] Owner $owner,
        string $id,
        #[MapRequestPayload] EditJournalEntryRequest $payload,
    ): Response {
        $this->commandBus->dispatch(new EditJournalEntry(
            JournalEntryId::fromString($id),
            $owner->ownerId(),
            $payload->title,
            $payload->entry,
            null === $payload->tagIds ? null : self::tagIds($payload->tagIds),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/journal-entries/{id}/archive → 204. Hides the entry from
     * lists; it can still be fetched by id and unarchived.
     *
     * @throws ExceptionInterface
     */
    #[Route('/journal-entries/{id}/archive', name: 'archive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function archive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new ArchiveJournalEntry(JournalEntryId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/journal-entries/{id}/unarchive → 204.
     *
     * @throws ExceptionInterface
     */
    #[Route('/journal-entries/{id}/unarchive', name: 'unarchive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function unarchive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new UnarchiveJournalEntry(JournalEntryId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @param list<string> $ids validated UUIDs
     *
     * @return list<TagId>
     */
    private static function tagIds(array $ids): array
    {
        return array_map(TagId::fromString(...), $ids);
    }
}
