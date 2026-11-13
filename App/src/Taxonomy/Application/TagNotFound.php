<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\NotFoundException;

/**
 * No tag with this id belongs to the caller (it doesn't exist, or it's
 * someone else's; the two look the same).
 */
final class TagNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Tag not found.');
    }
}
