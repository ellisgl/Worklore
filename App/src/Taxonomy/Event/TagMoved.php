<?php

declare(strict_types=1);

namespace App\Taxonomy\Event;

use App\Shared\EventSourcing\DomainEvent;
use App\Taxonomy\CategoryId;
use App\Taxonomy\TagId;

/**
 * The tag moved to another of the owner's categories. Stored as "tag.moved".
 */
final readonly class TagMoved implements DomainEvent
{
    public function __construct(
        public TagId $tagId,
        public CategoryId $categoryId,
    ) {
    }
}
