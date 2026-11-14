<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\ProjectId;
use App\Shared\EventSourcing\DomainEvent;
use DateTimeImmutable;

/**
 * The project was archived. Stored as "project.archived".
 */
final readonly class ProjectArchived implements DomainEvent
{
    public function __construct(
        public ProjectId $projectId,
        public DateTimeImmutable $archivedAt,
    ) {
    }
}
