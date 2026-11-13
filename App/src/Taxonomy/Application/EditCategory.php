<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\UserId;
use App\Taxonomy\CategoryId;

/**
 * Command: change a category's details. Fields left null are unchanged; an
 * empty description clears it. Applied in one transaction.
 */
final readonly class EditCategory
{
    public function __construct(
        public CategoryId $categoryId,
        public UserId $ownerId,
        public ?string $name = null,
        public ?string $description = null,
    ) {
    }
}
