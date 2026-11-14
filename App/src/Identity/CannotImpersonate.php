<?php

declare(strict_types=1);

namespace App\Identity;

use App\Shared\InvalidValueException;

final class CannotImpersonate extends InvalidValueException
{
    public static function self(): self
    {
        return new self('You can\'t impersonate yourself.');
    }

    public static function admin(): self
    {
        return new self('Admins can\'t be impersonated.');
    }

    public static function archived(): self
    {
        return new self('Archived users can\'t be impersonated; unarchive them first.');
    }
}
