<?php

declare(strict_types=1);

namespace App\Identity;

use App\Shared\InvalidValueException;

/**
 * The current password given to confirm a change doesn't match.
 */
final class IncorrectPassword extends InvalidValueException
{
    public function __construct()
    {
        parent::__construct('Current password is incorrect.');
    }
}
