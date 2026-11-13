<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Company\Application\ArchivePosition;
use App\Company\Application\CompanyNotFound;
use App\Company\Application\CreatePosition;
use App\Company\Application\EditPosition;
use App\Company\Application\EndPosition;
use App\Company\Application\PositionNotFound;
use App\Company\Application\ReopenPosition;
use App\Company\Application\UnarchivePosition;
use App\Company\CompanyId;
use App\Company\PositionId;
use App\Company\Query\CompanyQueries;
use App\Company\Query\PositionQueries;
use App\Shared\Http\PageRequest;
use App\Shared\InvalidValueException;
use App\Shared\Owner;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
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
 * Positions: listed and created under their company, read and changed by id.
 * Ending and reopening have their own endpoints. Dates are YYYY-MM-DD.
 */
#[Route('/api', name: 'api_positions_')]
final class PositionController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
        private readonly PositionQueries $positions,
        private readonly CompanyQueries $companies,
    ) {
    }

    /**
     * GET /api/companies/{companyId}/positions[?include_archived=1][&page=1&per_page=50], newest
     * start date first.
     *
     * @throws CompanyNotFound
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    #[Route('/companies/{companyId}/positions', name: 'list', requirements: ['companyId' => Requirement::UUID], methods: ['GET'])]
    public function list(
        #[CurrentUser] Owner $owner,
        string $companyId,
        #[MapQueryParameter(name: 'include_archived')] bool $includeArchived = false,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        $company = CompanyId::fromString($companyId);
        if (!$this->companies->existsForOwner($owner->ownerId(), $company)) {
            throw new CompanyNotFound();
        }

        return $this->json($this->positions->listForCompany($owner->ownerId(), $company, $includeArchived, $paging->toPage()));
    }

    /**
     * GET /api/positions/current[?page=1&per_page=50]: the caller's current positions across all
     * companies (archived ones left out), newest start date first.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     * @throws InvalidValueException
     */
    #[Route('/positions/current', name: 'current', methods: ['GET'])]
    public function current(
        #[CurrentUser] Owner $owner,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        return $this->json($this->positions->listCurrentForOwner($owner->ownerId(), $paging->toPage()));
    }

    /**
     * POST /api/companies/{companyId}/positions
     * {"title": "...", "start_date": "...", "overview": "...", "end_date": "...", "reason_left": "..."}
     * → 201 {"id": "..."}. With end_date the position is recorded as ended.
     *
     * @throws ExceptionInterface
     * @throws InvalidValueException
     */
    #[Route('/companies/{companyId}/positions', name: 'create', requirements: ['companyId' => Requirement::UUID], methods: ['POST'])]
    public function create(
        #[CurrentUser] Owner $owner,
        string $companyId,
        #[MapRequestPayload] CreatePositionRequest $payload,
    ): JsonResponse {
        $id = PositionId::generate();
        $this->commandBus->dispatch(new CreatePosition(
            $id,
            CompanyId::fromString($companyId),
            $owner->ownerId(),
            $payload->title,
            self::date($payload->startDate),
            $payload->overview,
            null === $payload->endDate ? null : self::date($payload->endDate),
            $payload->reasonLeft,
        ));

        return $this->json(['id' => $id->toRfc4122()], Response::HTTP_CREATED);
    }

    /**
     * GET /api/positions/{id}: one of the caller's positions, archived or not.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws PositionNotFound
     */
    #[Route('/positions/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(#[CurrentUser] Owner $owner, string $id): JsonResponse
    {
        return $this->json($this->positions->findForOwner($owner->ownerId(), PositionId::fromString($id)) ?? throw new PositionNotFound());
    }

    /**
     * PATCH /api/positions/{id} {"title": "...", "overview": "...", "start_date": "..."} → 204.
     * Only the fields sent are changed; an empty overview clears it.
     *
     * @throws ExceptionInterface
     * @throws InvalidValueException
     */
    #[Route('/positions/{id}', name: 'edit', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function edit(
        #[CurrentUser] Owner $owner,
        string $id,
        #[MapRequestPayload] EditPositionRequest $payload,
    ): Response {
        $this->commandBus->dispatch(new EditPosition(
            PositionId::fromString($id),
            $owner->ownerId(),
            $payload->title,
            $payload->overview,
            null === $payload->startDate ? null : self::date($payload->startDate),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/positions/{id}/end {"end_date": "...", "reason_left": "..."} → 204.
     * Ending an ended position corrects its end date and reason.
     *
     * @throws ExceptionInterface
     * @throws InvalidValueException
     */
    #[Route('/positions/{id}/end', name: 'end', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function end(
        #[CurrentUser] Owner $owner,
        string $id,
        #[MapRequestPayload] EndPositionRequest $payload,
    ): Response {
        $this->commandBus->dispatch(new EndPosition(PositionId::fromString($id), $owner->ownerId(), self::date($payload->endDate), $payload->reasonLeft));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/positions/{id}/reopen → 204. Makes the position current
     * again, clearing the end date and reason.
     *
     * @throws ExceptionInterface
     */
    #[Route('/positions/{id}/reopen', name: 'reopen', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function reopen(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new ReopenPosition(PositionId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/positions/{id}/archive → 204. Hides the position from lists;
     * it can still be fetched by id and unarchived.
     *
     * @throws ExceptionInterface
     */
    #[Route('/positions/{id}/archive', name: 'archive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function archive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new ArchivePosition(PositionId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/positions/{id}/unarchive → 204.
     *
     * @throws ExceptionInterface
     */
    #[Route('/positions/{id}/unarchive', name: 'unarchive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function unarchive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new UnarchivePosition(PositionId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * A validated YYYY-MM-DD string as midnight UTC.
     *
     * @throws InvalidValueException if it isn't a date (the request validation should have caught it)
     */
    private static function date(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'))
            ?: throw new InvalidValueException('Invalid date: '.$value);
    }
}
