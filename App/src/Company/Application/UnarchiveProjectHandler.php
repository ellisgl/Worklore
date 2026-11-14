<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProjectRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveProjectHandler
{
    public function __construct(
        private ProjectRepository $projects,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws ProjectNotFound
     */
    public function __invoke(UnarchiveProject $command): void
    {
        $project = $this->projects->findForOwner($command->projectId, $command->ownerId)
            ?? throw new ProjectNotFound();

        $project->unarchive();
        $this->projects->save($project);
    }
}
