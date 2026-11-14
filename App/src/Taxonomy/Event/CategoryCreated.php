<?php

declare(strict_types=1);

namespace App\Taxonomy\Event;

use App\Shared\EventSourcing\DomainEvent;
use App\Shared\UserId;
use App\Taxonomy\CategoryId;
use DateTimeImmutable;

/**
 * A category was added. Stored as "category.created".
 */
final readonly class CategoryCreated implements DomainEvent
{
    public function __construct(
        public CategoryId $categoryId,
        public UserId $ownerId,
        public string $name,
        public ?string $description,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
