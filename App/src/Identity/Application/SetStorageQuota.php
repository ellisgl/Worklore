<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\UserId;

/**
 * Give a user their own media quota, or (null) put them back on the default.
 */
final readonly class SetStorageQuota
{
    public function __construct(
        public UserId $userId,
        public ?int $bytes,
    ) {
    }
}
