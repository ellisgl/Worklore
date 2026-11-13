<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\UserId;
use App\Taxonomy\TagId;

/**
 * Command: unarchive one of the owner's tags.
 * Unarchiving an active tag does nothing.
 */
final readonly class UnarchiveTag
{
    public function __construct(
        public TagId $tagId,
        public UserId $ownerId,
    ) {
    }
}
