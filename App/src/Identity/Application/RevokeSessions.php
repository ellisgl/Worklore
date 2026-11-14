<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\UserId;

/**
 * Command: end every session of a user (log out everywhere).
 */
final readonly class RevokeSessions
{
    public function __construct(
        public UserId $userId,
    ) {
    }
}
