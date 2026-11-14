<?php

declare(strict_types=1);

namespace App\Taxonomy\Event;

use App\Shared\EventSourcing\DomainEvent;
use App\Taxonomy\CategoryId;

/**
 * The category's description changed (null: cleared). Stored as "category.description_changed".
 */
final readonly class CategoryDescriptionChanged implements DomainEvent
{
    public function __construct(
        public CategoryId $categoryId,
        public ?string $description,
    ) {
    }
}
