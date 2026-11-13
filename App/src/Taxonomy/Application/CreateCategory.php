<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\UserId;
use App\Taxonomy\CategoryId;

/**
 * Command: create a category for an owner. The caller picks the id, so
 * dispatching returns nothing. Category names needn't be unique.
 */
final readonly class CreateCategory
{
    public function __construct(
        public CategoryId $categoryId,
        public UserId $ownerId,
        public string $name,
        public ?string $description = null,
    ) {
    }
}
