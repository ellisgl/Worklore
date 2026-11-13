<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\ConflictException;

use function sprintf;
use function trim;

/**
 * The owner already has a category with this name, ignoring case
 * (archived ones included).
 */
final class CategoryNameTaken extends ConflictException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('You already have a category named "%s".', trim($name)));
    }
}
