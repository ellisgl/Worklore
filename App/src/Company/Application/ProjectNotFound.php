<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Shared\NotFoundException;

/**
 * No project with this id belongs to the caller (it doesn't exist, or it's
 * someone else's; the two look the same).
 */
final class ProjectNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Project not found.');
    }
}
