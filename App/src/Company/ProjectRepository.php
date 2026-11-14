<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\EventSourcing\AggregateStore;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\UserId;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Projects, loaded from and saved to their event streams.
 */
final readonly class ProjectRepository
{
    public function __construct(private AggregateStore $store)
    {
    }

    /**
     * The project, if it exists and belongs to the owner; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too.
     *
     * @throws Exception
     */
    public function findForOwner(ProjectId $id, UserId $ownerId): ?Project
    {
        $history = $this->store->history(CompanyEvents::PROJECT, $id);
        if ([] === $history) {
            return null;
        }
        $project = Project::fromHistory($history);

        return $project->ownerId()->equals($ownerId) ? $project : null;
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function save(Project $project): void
    {
        $this->store->save(CompanyEvents::PROJECT, $project);
    }
}
