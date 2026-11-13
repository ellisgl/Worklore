<?php

declare(strict_types=1);

namespace App\Taxonomy\Doctrine;

use App\Taxonomy\TagId;
use App\Shared\Doctrine\EntityIdType;

final class TagIdType extends EntityIdType
{
    public const string NAME = 'tag_id';

    protected function idClass(): string
    {
        return TagId::class;
    }
}
