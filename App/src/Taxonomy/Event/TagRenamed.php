<?php

declare(strict_types=1);

namespace App\Taxonomy\Event;

use App\Shared\EventSourcing\DomainEvent;
use App\Taxonomy\TagId;

/**
 * The tag's name changed. Stored as "tag.renamed".
 */
final readonly class TagRenamed implements DomainEvent
{
    public function __construct(
        public TagId $tagId,
        public string $name,
    ) {
    }
}
