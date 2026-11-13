<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\UserId;
use App\Taxonomy\CategoryId;
use App\Taxonomy\TagId;

/**
 * Command: add a tag to one of the owner's categories. The caller picks the
 * id, so dispatching returns nothing.
 */
final readonly class CreateTag
{
    public function __construct(
        public TagId $tagId,
        public CategoryId $categoryId,
        public UserId $ownerId,
        public string $name,
    ) {
    }
}
