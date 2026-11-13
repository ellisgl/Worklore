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
final readonly class ArchiveProjectHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ORMException
     * @throws ProjectNotFound
     */
    public function __invoke(ArchiveProject $command): void
    {
        $project = $this->companies->findProjectByIdForOwner($command->projectId, $this->owners->get($command->ownerId))
            ?? throw new ProjectNotFound();

        $project->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
    }
}
