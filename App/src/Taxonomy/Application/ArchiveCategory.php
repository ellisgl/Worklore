<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\UserId;
use App\Taxonomy\CategoryId;

/**
 * Command: archive one of the owner's categories.
 * Archiving an archived category keeps its original archive time.
 */
final readonly class ArchiveCategory
{
    public function __construct(
        public CategoryId $categoryId,
        public UserId $ownerId,
    ) {
    }
}
