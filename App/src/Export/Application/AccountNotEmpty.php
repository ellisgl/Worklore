<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Shared\ConflictException;

final class AccountNotEmpty extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Imports go into an account with no records. Sign up for a new account (or delete this one\'s records) first.');
    }
}
