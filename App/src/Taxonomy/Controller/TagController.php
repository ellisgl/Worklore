<?php

declare(strict_types=1);

namespace App\Taxonomy\Controller;

use App\Shared\Http\PageRequest;
use App\Shared\InvalidValueException;
use App\Shared\Owner;
use App\Taxonomy\Application\ArchiveTag;
use App\Taxonomy\Application\CategoryNotFound;
use App\Taxonomy\Application\CreateTag;
use App\Taxonomy\Application\EditTag;
use App\Taxonomy\Application\TagNotFound;
use App\Taxonomy\Application\UnarchiveTag;
use App\Taxonomy\CategoryId;
use App\Taxonomy\Query\CategoryQueries;
use App\Taxonomy\Query\TagQueries;
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

/**
 * Tags: listed and created under their category (or all at once), read and
 * changed by id. Reads call the query classes; writes dispatch a command.
 */
#[Route('/api', name: 'api_tags_')]
final class TagController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
        private readonly TagQueries $tags,
        private readonly CategoryQueries $categories,
    ) {
    }

    /**
     * GET /api/tags[?include_archived=1][&page=1&per_page=50]: all the
     * caller's tags, sorted by category and then name.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    #[Route('/tags', name: 'list_all', methods: ['GET'])]
    public function listAll(
        #[CurrentUser] Owner $owner,
        #[MapQueryParameter(name: 'include_archived')] bool $includeArchived = false,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        return $this->json($this->tags->listForOwner($owner->ownerId(), $includeArchived, $paging->toPage()));
    }

    /**
     * GET /api/categories/{categoryId}/tags[?include_archived=1][&page=1&per_page=50]
     *
     * @throws CategoryNotFound
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    #[Route('/categories/{categoryId}/tags', name: 'list', requirements: ['categoryId' => Requirement::UUID], methods: ['GET'])]
    public function list(
        #[CurrentUser] Owner $owner,
        string $categoryId,
        #[MapQueryParameter(name: 'include_archived')] bool $includeArchived = false,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        $category = CategoryId::fromString($categoryId);
        if (!$this->categories->existsForOwner($owner->ownerId(), $category)) {
            throw new CategoryNotFound();
        }

        return $this->json($this->tags->listForCategory($owner->ownerId(), $category, $includeArchived, $paging->toPage()));
    }

    /**
     * POST /api/categories/{categoryId}/tags {"name": "..."} → 201 {"id": "..."}.
     * 409 if the category already has a tag with that name, ignoring case.
     *
     * @throws ExceptionInterface
     */
    #[Route('/categories/{categoryId}/tags', name: 'create', requirements: ['categoryId' => Requirement::UUID], methods: ['POST'])]
    public function create(
        #[CurrentUser] Owner $owner,
        string $categoryId,
        #[MapRequestPayload] CreateTagRequest $payload,
    ): JsonResponse {
        $id = TagId::generate();
        $this->commandBus->dispatch(new CreateTag($id, CategoryId::fromString($categoryId), $owner->ownerId(), $payload->name));

        return $this->json(['id' => $id->toRfc4122()], Response::HTTP_CREATED);
    }

    /**
     * GET /api/tags/{id}: one of the caller's tags, archived or not.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws TagNotFound
     */
    #[Route('/tags/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(#[CurrentUser] Owner $owner, string $id): JsonResponse
    {
        return $this->json($this->tags->findForOwner($owner->ownerId(), TagId::fromString($id)) ?? throw new TagNotFound());
    }

    /**
     * PATCH /api/tags/{id} {"name": "...", "category_id": "..."} → 204.
     * Renames and/or moves the tag; 409 if the name is taken in the category
     * it ends up in.
     *
     * @throws ExceptionInterface
     */
    #[Route('/tags/{id}', name: 'edit', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function edit(
        #[CurrentUser] Owner $owner,
        string $id,
        #[MapRequestPayload] EditTagRequest $payload,
    ): Response {
        $this->commandBus->dispatch(new EditTag(
            TagId::fromString($id),
            $owner->ownerId(),
            $payload->name,
            null === $payload->categoryId ? null : CategoryId::fromString($payload->categoryId),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/tags/{id}/archive → 204. Hides the tag from lists; it can
     * still be fetched by id and unarchived. Its name stays taken.
     *
     * @throws ExceptionInterface
     */
    #[Route('/tags/{id}/archive', name: 'archive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function archive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new ArchiveTag(TagId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/tags/{id}/unarchive → 204.
     *
     * @throws ExceptionInterface
     */
    #[Route('/tags/{id}/unarchive', name: 'unarchive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function unarchive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new UnarchiveTag(TagId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
