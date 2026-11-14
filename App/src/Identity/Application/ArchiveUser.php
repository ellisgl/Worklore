<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\UserId;

/**
 * Command: block a user from logging in and end their sessions; their data stays.
 */
final readonly class ArchiveUser
{
    public function __construct(
        public UserId $userId,
    ) {
    }
}
