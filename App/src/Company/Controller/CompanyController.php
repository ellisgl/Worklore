<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Company\Application\ArchiveCompany;
use App\Company\Application\CompanyNotFound;
use App\Company\Application\CreateCompany;
use App\Company\Application\EditCompany;
use App\Company\Application\UnarchiveCompany;
use App\Company\CompanyId;
use App\Company\Query\CompanyQueries;
use App\Company\Query\CompanySummary;
use App\Shared\Http\OpenApi\Conflict;
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
 * Reads call the query class; writes dispatch a command. Domain exceptions
 * become 404/409/422 in App\Shared\Http\DomainExceptionListener.
 */
#[OA\Tag(name: 'Companies')]
#[Route('/api/companies', name: 'api_companies_')]
final class CompanyController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
        private readonly CompanyQueries $queries,
    ) {
    }

    /**
     * List the caller's companies.
     *
     * GET /api/companies[?include_archived=1][&page=1&per_page=50]
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    #[PageOf(CompanySummary::class, 'The companies, sorted by name')]
    #[Invalid]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[CurrentUser] Owner $owner,
        #[MapQueryParameter(name: 'include_archived')] bool $includeArchived = false,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        return $this->json($this->queries->listForOwner($owner->ownerId(), $includeArchived, $paging->toPage()));
    }

    /**
     * Get a company.
     *
     * GET /api/companies/{id}: one of the caller's companies, archived or not.
     *
     * @throws CompanyNotFound
     * @throws DateMalformedStringException
     * @throws Exception
     */
    #[Item(CompanySummary::class, 'The company')]
    #[NotFound('Company not found.')]
    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(#[CurrentUser] Owner $owner, string $id): JsonResponse
    {
        $company = $this->queries->findForOwner($owner->ownerId(), CompanyId::fromString($id))
            ?? throw new CompanyNotFound();

        return $this->json($company);
    }

    /**
     * Create a company.
     *
     * POST /api/companies {"name": "...", "overview": "..."} → 201 {"id": "..."}
     *
     * @throws ExceptionInterface
     */
    #[Created('Created')]
    #[Invalid]
    #[Conflict('The caller already has a company with this name, ignoring case', 'You already have a company named "Acme".')]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[CurrentUser] Owner $owner,
        #[MapRequestPayload] CreateCompanyRequest $payload,
    ): JsonResponse {
        $id = CompanyId::generate();
        $this->commandBus->dispatch(new CreateCompany($id, $owner->ownerId(), $payload->name, $payload->overview));

        return $this->json(['id' => $id->toRfc4122()], Response::HTTP_CREATED);
    }

    /**
     * Change a company's name or overview.
     *
     * PATCH /api/companies/{id} {"name": "...", "overview": "..."} → 204.
     * Only the fields sent are changed; an empty overview clears it.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Saved')]
    #[Invalid]
    #[NotFound('Company not found.')]
    #[Conflict('The caller already has a company with this name, ignoring case', 'You already have a company named "Acme".')]
    #[Route('/{id}', name: 'edit', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function edit(
        #[CurrentUser] Owner $owner,
        string $id,
        #[MapRequestPayload] EditCompanyRequest $payload,
    ): Response {
        $this->commandBus->dispatch(new EditCompany(CompanyId::fromString($id), $owner->ownerId(), $payload->name, $payload->overview));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Archive a company.
     *
     * POST /api/companies/{id}/archive → 204. Hides the company (and what's
     * inside it) from lists; it can still be fetched by id and unarchived.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Archived')]
    #[NotFound('Company not found.')]
    #[Route('/{id}/archive', name: 'archive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function archive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new ArchiveCompany(CompanyId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Unarchive a company.
     *
     * POST /api/companies/{id}/unarchive → 204.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Unarchived')]
    #[NotFound('Company not found.')]
    #[Route('/{id}/unarchive', name: 'unarchive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function unarchive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new UnarchiveCompany(CompanyId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
