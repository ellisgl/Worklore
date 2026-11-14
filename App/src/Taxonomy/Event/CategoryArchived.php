<?php

declare(strict_types=1);

namespace App\Taxonomy\Event;

use App\Shared\EventSourcing\DomainEvent;
use App\Taxonomy\CategoryId;
use DateTimeImmutable;

/**
 * The category was archived. Stored as "category.archived".
 */
final readonly class CategoryArchived implements DomainEvent
{
    public function __construct(
        public CategoryId $categoryId,
        public DateTimeImmutable $archivedAt,
    ) {
    }
}
