<?php

declare(strict_types=1);

namespace App\Taxonomy\Doctrine;

use App\Taxonomy\CategoryId;
use App\Shared\Doctrine\EntityIdType;

final class CategoryIdType extends EntityIdType
{
    public const string NAME = 'category_id';

    protected function idClass(): string
    {
        return CategoryId::class;
    }
}
