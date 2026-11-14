<?php

declare(strict_types=1);

namespace App\Taxonomy\Event;

use App\Shared\EventSourcing\DomainEvent;
use App\Shared\UserId;
use App\Taxonomy\CategoryId;
use App\Taxonomy\TagId;
use DateTimeImmutable;

/**
 * A tag was added to a category. Stored as "tag.created".
 */
final readonly class TagCreated implements DomainEvent
{
    public function __construct(
        public TagId $tagId,
        public CategoryId $categoryId,
        public UserId $ownerId,
        public string $name,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
