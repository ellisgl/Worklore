<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\UserId;
use App\Taxonomy\CategoryId;
use App\Taxonomy\TagId;

/**
 * Command: rename a tag and/or move it to another of the owner's
 * categories. Fields left null are unchanged. Applied in one transaction.
 */
final readonly class EditTag
{
    public function __construct(
        public TagId $tagId,
        public UserId $ownerId,
        public ?string $name = null,
        public ?CategoryId $categoryId = null,
    ) {
    }
}
