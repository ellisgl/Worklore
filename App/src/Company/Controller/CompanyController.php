<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Company\Application\CompanyNotFound;
use App\Company\Application\CreateCompany;
use App\Company\Application\EditCompany;
use App\Company\CompanyId;
use App\Company\Query\CompanyQueries;
use App\Shared\Owner;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
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
     * GET /api/companies[?include_archived=1]
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[CurrentUser] Owner $owner,
        #[MapQueryParameter(name: 'include_archived')] bool $includeArchived = false,
    ): JsonResponse {
        return $this->json($this->queries->listForOwner($owner->ownerId(), $includeArchived));
    }

    /**
     * GET /api/companies/{id}: one of the caller's companies, archived or not.
     *
     * @throws CompanyNotFound
     * @throws DateMalformedStringException
     * @throws Exception
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(#[CurrentUser] Owner $owner, string $id): JsonResponse
    {
        $company = $this->queries->findForOwner($owner->ownerId(), CompanyId::fromString($id))
            ?? throw new CompanyNotFound();

        return $this->json($company);
    }

    /**
     * POST /api/companies {"name": "...", "overview": "..."} → 201 {"id": "..."}
     *
     * @throws ExceptionInterface
     */
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
     * PATCH /api/companies/{id} {"name": "...", "overview": "..."} → 204.
     * Only the fields sent are changed; an empty overview clears it.
     *
     * @throws ExceptionInterface
     */
    #[Route('/{id}', name: 'edit', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function edit(
        #[CurrentUser] Owner $owner,
        string $id,
        #[MapRequestPayload] EditCompanyRequest $payload,
    ): Response {
        $this->commandBus->dispatch(new EditCompany(CompanyId::fromString($id), $owner->ownerId(), $payload->name, $payload->overview));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
