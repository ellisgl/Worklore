<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use DateTimeZone;
use Doctrine\ORM\Exception\ORMException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchivePositionHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ORMException
     * @throws PositionNotFound
     */
    public function __invoke(ArchivePosition $command): void
    {
        $position = $this->companies->findPositionByIdForOwner($command->positionId, $this->owners->get($command->ownerId))
            ?? throw new PositionNotFound();

        $position->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
    }
}
