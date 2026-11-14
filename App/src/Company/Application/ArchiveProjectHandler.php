<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProjectRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveProjectHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws ProjectNotFound
     */
    public function __invoke(ArchiveProject $command): void
    {
        $project = $this->projects->findForOwner($command->projectId, $command->ownerId)
            ?? throw new ProjectNotFound();

        $project->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
        $this->projects->save($project);
    }
}
