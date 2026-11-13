<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchivePositionHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws ORMException
     * @throws PositionNotFound
     */
    public function __invoke(UnarchivePosition $command): void
    {
        $position = $this->companies->findPositionByIdForOwner($command->positionId, $this->owners->get($command->ownerId))
            ?? throw new PositionNotFound();

        $position->unarchive();
    }
}
