<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\ProjectId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The project was unarchived. Stored as "project.unarchived".
 */
final readonly class ProjectUnarchived implements DomainEvent
{
    public function __construct(
        public ProjectId $projectId,
    ) {
    }
}
