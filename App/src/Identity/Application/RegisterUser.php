<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\Secret;
use App\Shared\UserId;

/**
 * Command: create an account. The caller picks the id. Fails with UserAlreadyExistsException if the email is taken.
 */
final readonly class RegisterUser
{
    /**
     * @param list<string> $roles extra roles (ROLE_USER is implicit)
     */
    public function __construct(
        public UserId $userId,
        public string $email,
        public Secret $password,
        public array $roles = [],
    ) {
    }
}
