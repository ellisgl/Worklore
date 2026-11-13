<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\UserId;
use App\Taxonomy\CategoryId;

/**
 * Command: unarchive one of the owner's categories.
 * Unarchiving an active category does nothing.
 */
final readonly class UnarchiveCategory
{
    public function __construct(
        public CategoryId $categoryId,
        public UserId $ownerId,
    ) {
    }
}
