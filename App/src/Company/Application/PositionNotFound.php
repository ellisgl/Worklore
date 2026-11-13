<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Shared\NotFoundException;

/**
 * No position with this id belongs to the caller (it doesn't exist, or it's
 * someone else's; the two look the same).
 */
final class PositionNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Position not found.');
    }
}
