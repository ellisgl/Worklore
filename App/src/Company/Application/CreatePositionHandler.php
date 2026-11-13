<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Adds the position through its company (the aggregate root); the bus
 * commits it, or nothing if the dates are invalid.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreatePositionHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws CompanyNotFound
     * @throws InvalidValueException if the title is blank or too long, or the position would end before it starts
     * @throws ORMException
     */
    public function __invoke(CreatePosition $command): void
    {
        $company = $this->companies->findOneByIdForOwner($command->companyId, $this->owners->get($command->ownerId))
            ?? throw new CompanyNotFound();

        $position = $company->addPosition($command->title, $command->startDate, $command->overview, id: $command->positionId);
        if (null !== $command->endDate) {
            $position->end($command->endDate, $command->reasonLeft);
        }
    }
}
