<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\UserId;

/**
 * Command: delete a user, their sessions and everything they own (the event history included).
 */
final readonly class DeleteUser
{
    public function __construct(
        public UserId $userId,
    ) {
    }
}
