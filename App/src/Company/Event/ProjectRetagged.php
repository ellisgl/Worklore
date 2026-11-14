<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\ProjectId;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\EventSourcing\ListOf;
use App\Taxonomy\TagId;

/**
 * The project's tags were replaced (sorted by id; may be empty). Stored as "project.retagged".
 */
final readonly class ProjectRetagged implements DomainEvent
{
    /**
     * @param list<TagId> $tagIds
     */
    public function __construct(
        public ProjectId $projectId,
        #[ListOf(TagId::class)]
        public array $tagIds,
    ) {
    }
}
