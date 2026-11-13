<?php

declare(strict_types=1);

namespace App\Taxonomy\Controller;

use App\Shared\Http\PageRequest;
use App\Shared\InvalidValueException;
use App\Shared\Owner;
use App\Taxonomy\Application\ArchiveCategory;
use App\Taxonomy\Application\CategoryNotFound;
use App\Taxonomy\Application\CreateCategory;
use App\Taxonomy\Application\EditCategory;
use App\Taxonomy\Application\UnarchiveCategory;
use App\Taxonomy\CategoryId;
use App\Taxonomy\Query\CategoryQueries;
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
 * Categories group tags (e.g. "Skill": php, sql). Reads call the query
 * class; writes dispatch a command.
 */
#[Route('/api/categories', name: 'api_categories_')]
final class CategoryController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
        private readonly CategoryQueries $queries,
    ) {
    }

    /**
     * GET /api/categories[?include_archived=1][&page=1&per_page=50]
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[CurrentUser] Owner $owner,
        #[MapQueryParameter(name: 'include_archived')] bool $includeArchived = false,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        return $this->json($this->queries->listForOwner($owner->ownerId(), $includeArchived, $paging->toPage()));
    }

    /**
     * POST /api/categories {"name": "...", "description": "..."} → 201 {"id": "..."}
     *
     * @throws ExceptionInterface
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[CurrentUser] Owner $owner,
        #[MapRequestPayload] CreateCategoryRequest $payload,
    ): JsonResponse {
        $id = CategoryId::generate();
        $this->commandBus->dispatch(new CreateCategory($id, $owner->ownerId(), $payload->name, $payload->description));

        return $this->json(['id' => $id->toRfc4122()], Response::HTTP_CREATED);
    }

    /**
     * GET /api/categories/{id}: one of the caller's categories, archived or not.
     *
     * @throws CategoryNotFound
     * @throws DateMalformedStringException
     * @throws Exception
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(#[CurrentUser] Owner $owner, string $id): JsonResponse
    {
        return $this->json($this->queries->findForOwner($owner->ownerId(), CategoryId::fromString($id)) ?? throw new CategoryNotFound());
    }

    /**
     * PATCH /api/categories/{id} {"name": "...", "description": "..."} → 204.
     * Only the fields sent are changed; an empty description clears it.
     *
     * @throws ExceptionInterface
     */
    #[Route('/{id}', name: 'edit', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function edit(
        #[CurrentUser] Owner $owner,
        string $id,
        #[MapRequestPayload] EditCategoryRequest $payload,
    ): Response {
        $this->commandBus->dispatch(new EditCategory(CategoryId::fromString($id), $owner->ownerId(), $payload->name, $payload->description));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/categories/{id}/archive → 204. Hides the category (and its
     * tags) from lists; it can still be fetched by id and unarchived.
     *
     * @throws ExceptionInterface
     */
    #[Route('/{id}/archive', name: 'archive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function archive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new ArchiveCategory(CategoryId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/categories/{id}/unarchive → 204.
     *
     * @throws ExceptionInterface
     */
    #[Route('/{id}/unarchive', name: 'unarchive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function unarchive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new UnarchiveCategory(CategoryId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
