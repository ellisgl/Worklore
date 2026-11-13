<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\NotFoundException;

/**
 * No category with this id belongs to the caller (it doesn't exist, or it's
 * someone else's; the two look the same).
 */
final class CategoryNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Category not found.');
    }
}
