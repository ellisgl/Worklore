<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Shared\ConflictException;

use function sprintf;

/**
 * The owner already has a company with this name, ignoring case.
 */
final class CompanyNameTaken extends ConflictException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('You already have a company named "%s".', trim($name)));
    }
}
