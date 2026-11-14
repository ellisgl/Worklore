<?php

declare(strict_types=1);

namespace App\Taxonomy\Event;

use App\Shared\EventSourcing\DomainEvent;
use App\Taxonomy\TagId;
use DateTimeImmutable;

/**
 * The tag was archived. Stored as "tag.archived".
 */
final readonly class TagArchived implements DomainEvent
{
    public function __construct(
        public TagId $tagId,
        public DateTimeImmutable $archivedAt,
    ) {
    }
}
