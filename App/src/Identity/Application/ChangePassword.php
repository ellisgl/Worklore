<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\Secret;
use App\Shared\UserId;

/**
 * Command: set a new password and end every session. With a current password (the account owner changing it), that must match; without one (an administrator) it is not checked.
 */
final readonly class ChangePassword
{
    public function __construct(
        public UserId $userId,
        public Secret $newPassword,
        public ?Secret $currentPassword = null,
    ) {
    }
}
