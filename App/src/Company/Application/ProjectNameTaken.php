<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Shared\ConflictException;

use function sprintf;
use function trim;

/**
 * The product already has a project with this name, ignoring case
 * (archived ones included).
 */
final class ProjectNameTaken extends ConflictException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('This product already has a project named "%s".', trim($name)));
    }
}
