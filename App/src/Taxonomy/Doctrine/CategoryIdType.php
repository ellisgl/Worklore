<?php

declare(strict_types=1);

namespace App\Taxonomy\Doctrine;

use App\Shared\Doctrine\EntityIdType;
use App\Taxonomy\CategoryId;

final class CategoryIdType extends EntityIdType
{
    public const string NAME = 'category_id';

    protected function idClass(): string
    {
        return CategoryId::class;
    }
}
