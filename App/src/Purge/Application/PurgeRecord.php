<?php

declare(strict_types=1);

namespace App\Purge\Application;

use App\Purge\PurgeTarget;
use App\Shared\UserId;

/**
 * Erase a record, everything under it, and all their history, for good.
 * Only for an admin impersonating the owner (see PurgeController).
 */
final readonly class PurgeRecord
{
    public function __construct(
        public UserId $ownerId,
        public PurgeTarget $target,
        public string $id,
    ) {
    }
}
