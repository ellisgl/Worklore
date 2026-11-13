<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\UserId;
use App\Taxonomy\TagId;

/**
 * Command: archive one of the owner's tags.
 * Archiving an archived tag keeps its original archive time.
 */
final readonly class ArchiveTag
{
    public function __construct(
        public TagId $tagId,
        public UserId $ownerId,
    ) {
    }
}
