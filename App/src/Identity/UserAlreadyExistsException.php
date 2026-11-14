<?php

declare(strict_types=1);

namespace App\Identity;

use App\Shared\ConflictException;

/**
 * An account with this email exists already (409 over HTTP).
 */
final class UserAlreadyExistsException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('An account with this email already exists.');
    }
}
