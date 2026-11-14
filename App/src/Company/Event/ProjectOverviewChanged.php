<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\ProjectId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The project's overview changed (null: cleared). Stored as "project.overview_changed".
 */
final readonly class ProjectOverviewChanged implements DomainEvent
{
    public function __construct(
        public ProjectId $projectId,
        public ?string $overview,
    ) {
    }
}
