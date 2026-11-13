<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\ConflictException;

use function sprintf;
use function trim;

/**
 * The category already has a tag with this name, ignoring case (archived
 * tags included).
 */
final class TagNameTaken extends ConflictException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('This category already has a tag named "%s".', trim($name)));
    }
}
