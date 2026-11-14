<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\NotFoundException;

final class UserNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('User not found.');
    }
}
