<?php

declare(strict_types=1);

namespace App\Taxonomy\Doctrine;

use App\Shared\Doctrine\EntityIdType;
use App\Taxonomy\TagId;

final class TagIdType extends EntityIdType
{
    public const string NAME = 'tag_id';

    protected function idClass(): string
    {
        return TagId::class;
    }
}
