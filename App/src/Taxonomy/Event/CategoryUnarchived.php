<?php

declare(strict_types=1);

namespace App\Taxonomy\Event;

use App\Shared\EventSourcing\DomainEvent;
use App\Taxonomy\CategoryId;

/**
 * The category was unarchived. Stored as "category.unarchived".
 */
final readonly class CategoryUnarchived implements DomainEvent
{
    public function __construct(
        public CategoryId $categoryId,
    ) {
    }
}
