<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveProjectHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws ORMException
     * @throws ProjectNotFound
     */
    public function __invoke(UnarchiveProject $command): void
    {
        $project = $this->companies->findProjectByIdForOwner($command->projectId, $this->owners->get($command->ownerId))
            ?? throw new ProjectNotFound();

        $project->unarchive();
    }
}
