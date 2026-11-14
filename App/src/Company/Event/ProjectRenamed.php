<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\ProjectId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The project's name changed. Stored as "project.renamed".
 */
final readonly class ProjectRenamed implements DomainEvent
{
    public function __construct(
        public ProjectId $projectId,
        public string $name,
    ) {
    }
}
