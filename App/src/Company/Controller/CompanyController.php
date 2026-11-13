<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Company\Application\CreateCompany;
use App\Company\CompanyId;
use App\Shared\Owner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Writes dispatch a command. Domain exceptions
 * become 404/409/422 in App\Shared\Http\DomainExceptionListener.
 */
#[Route('/api/companies', name: 'api_companies_')]
final class CompanyController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    /**
     * POST /api/companies {"name": "...", "overview": "..."} → 201 {"id": "..."}
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
}
