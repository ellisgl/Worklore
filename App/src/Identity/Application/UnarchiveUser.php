<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\UserId;

/**
 * Command: let an archived user log in again.
 */
final readonly class UnarchiveUser
{
    public function __construct(
        public UserId $userId,
    ) {
    }
}
