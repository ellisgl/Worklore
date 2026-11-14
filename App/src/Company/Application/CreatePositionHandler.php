<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Company\Position;
use App\Company\PositionRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * With an end date the position is recorded as already ended; nothing is saved if the dates are invalid.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreatePositionHandler
{
    public function __construct(
        private PositionRepository $positions,
        private CompanyRepository $companies,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws CompanyNotFound
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the title is blank or too long, or the position would end before it starts
     */
    public function __invoke(CreatePosition $command): void
    {
        $company = $this->companies->findForOwner($command->companyId, $command->ownerId)
            ?? throw new CompanyNotFound();

        $this->positions->save(Position::record(
            $command->positionId,
            $company->id(),
            $command->ownerId,
            $command->title,
            $command->startDate,
            $command->overview,
            $command->endDate,
            $command->reasonLeft,
            $this->clock->now()->setTimezone(new DateTimeZone('UTC')),
        ));
    }
}
