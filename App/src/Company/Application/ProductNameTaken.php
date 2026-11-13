<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Shared\ConflictException;

use function sprintf;
use function trim;

/**
 * The company already has a product with this name, ignoring case
 * (archived ones included).
 */
final class ProductNameTaken extends ConflictException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('This company already has a product named "%s".', trim($name)));
    }
}
