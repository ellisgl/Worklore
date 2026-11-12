<?php

declare(strict_types=1);

namespace App\User;

final class UserAlreadyExistsException extends \RuntimeException
{
    public function __construct(string $email)
    {
        parent::__construct(\sprintf('A user with email "%s" already exists.', $email));
    }
}
