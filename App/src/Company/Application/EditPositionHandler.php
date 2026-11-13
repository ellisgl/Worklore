<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditPositionHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws InvalidValueException if the title is blank or too long, or the new start is after the end
     * @throws ORMException
     * @throws PositionNotFound
     */
    public function __invoke(EditPosition $command): void
    {
        $position = $this->companies->findPositionByIdForOwner($command->positionId, $this->owners->get($command->ownerId))
            ?? throw new PositionNotFound();

        if (null !== $command->title) {
            $position->setTitle($command->title);
        }
        if (null !== $command->overview) {
            $position->setOverview($command->overview);
        }
        if (null !== $command->startDate) {
            $position->setStartDate($command->startDate);
        }
    }
}
