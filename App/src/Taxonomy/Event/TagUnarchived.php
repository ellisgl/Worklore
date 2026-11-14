<?php

declare(strict_types=1);

namespace App\Taxonomy\Event;

use App\Shared\EventSourcing\DomainEvent;
use App\Taxonomy\TagId;

/**
 * The tag was unarchived. Stored as "tag.unarchived".
 */
final readonly class TagUnarchived implements DomainEvent
{
    public function __construct(
        public TagId $tagId,
    ) {
    }
}
