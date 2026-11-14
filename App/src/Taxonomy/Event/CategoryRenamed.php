<?php

declare(strict_types=1);

namespace App\Taxonomy\Event;

use App\Shared\EventSourcing\DomainEvent;
use App\Taxonomy\CategoryId;

/**
 * The category's name changed. Stored as "category.renamed".
 */
final readonly class CategoryRenamed implements DomainEvent
{
    public function __construct(
        public CategoryId $categoryId,
        public string $name,
    ) {
    }
}
