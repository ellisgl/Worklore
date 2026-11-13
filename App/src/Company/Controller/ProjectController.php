<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Company\Application\ArchiveProject;
use App\Company\Application\CreateProject;
use App\Company\Application\EditProject;
use App\Company\Application\ProductNotFound;
use App\Company\Application\ProjectNotFound;
use App\Company\Application\UnarchiveProject;
use App\Company\ProductId;
use App\Company\ProjectId;
use App\Company\Query\ProductQueries;
use App\Company\Query\ProjectQueries;
use App\Company\Query\ProjectSummary;
use App\Shared\Http\OpenApi\Created;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Http\OpenApi\NoContent;
use App\Shared\Http\OpenApi\NotFound;
use App\Shared\Http\OpenApi\PageOf;
use App\Shared\Http\PageRequest;
use App\Shared\InvalidValueException;
use App\Shared\Owner;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use OpenApi\Attributes as OA;
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
 * Projects: listed and created under their product, read and changed by id.
 * Reads call the query classes; writes dispatch a command.
 */
#[OA\Tag(name: 'Projects')]
#[Route('/api', name: 'api_projects_')]
final class ProjectController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
        private readonly ProjectQueries $projects,
        private readonly ProductQueries $products,
    ) {
    }

    /**
     * List a product's projects.
     *
     * GET /api/products/{productId}/projects[?include_archived=1][&page=1&per_page=50]
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     * @throws ProductNotFound
     */
    #[PageOf(ProjectSummary::class, 'The projects, sorted by name')]
    #[NotFound('Product not found.')]
    #[Invalid]
    #[Route('/products/{productId}/projects', name: 'list', requirements: ['productId' => Requirement::UUID], methods: ['GET'])]
    public function list(
        #[CurrentUser] Owner $owner,
        string $productId,
        #[MapQueryParameter(name: 'include_archived')] bool $includeArchived = false,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        $product = ProductId::fromString($productId);
        if (!$this->products->existsForOwner($owner->ownerId(), $product)) {
            throw new ProductNotFound();
        }

        return $this->json($this->projects->listForProduct($owner->ownerId(), $product, $includeArchived, $paging->toPage()));
    }

    /**
     * Add a project to a product.
     *
     * POST /api/products/{productId}/projects {"name": "...", "overview": "..."} → 201 {"id": "..."}
     *
     * @throws ExceptionInterface
     */
    #[Created('Created')]
    #[Invalid]
    #[NotFound('Product not found.')]
    #[Route('/products/{productId}/projects', name: 'create', requirements: ['productId' => Requirement::UUID], methods: ['POST'])]
    public function create(
        #[CurrentUser] Owner $owner,
        string $productId,
        #[MapRequestPayload] CreateProjectRequest $payload,
    ): JsonResponse {
        $id = ProjectId::generate();
        $this->commandBus->dispatch(new CreateProject($id, ProductId::fromString($productId), $owner->ownerId(), $payload->name, $payload->overview));

        return $this->json(['id' => $id->toRfc4122()], Response::HTTP_CREATED);
    }

    /**
     * Get a project.
     *
     * GET /api/projects/{id}: one of the caller's projects, archived or not.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws ProjectNotFound
     */
    #[Item(ProjectSummary::class, 'The project')]
    #[NotFound('Project not found.')]
    #[Route('/projects/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(#[CurrentUser] Owner $owner, string $id): JsonResponse
    {
        return $this->json($this->projects->findForOwner($owner->ownerId(), ProjectId::fromString($id)) ?? throw new ProjectNotFound());
    }

    /**
     * Change a project's name or overview.
     *
     * PATCH /api/projects/{id} {"name": "...", "overview": "..."} → 204.
     * Only the fields sent are changed; an empty overview clears it.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Saved')]
    #[Invalid]
    #[NotFound('Project not found.')]
    #[Route('/projects/{id}', name: 'edit', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function edit(
        #[CurrentUser] Owner $owner,
        string $id,
        #[MapRequestPayload] EditProjectRequest $payload,
    ): Response {
        $this->commandBus->dispatch(new EditProject(ProjectId::fromString($id), $owner->ownerId(), $payload->name, $payload->overview));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Archive a project.
     *
     * POST /api/projects/{id}/archive → 204. Hides the project (and its
     * journal entries) from lists; it can still be fetched by id and
     * unarchived.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Archived')]
    #[NotFound('Project not found.')]
    #[Route('/projects/{id}/archive', name: 'archive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function archive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new ArchiveProject(ProjectId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Unarchive a project.
     *
     * POST /api/projects/{id}/unarchive → 204.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Unarchived')]
    #[NotFound('Project not found.')]
    #[Route('/projects/{id}/unarchive', name: 'unarchive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function unarchive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new UnarchiveProject(ProjectId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
