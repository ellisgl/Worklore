<?php

declare(strict_types=1);

namespace App\Company\Doctrine;

use App\Company\PositionId;
use App\Shared\Doctrine\EntityIdType;

final class PositionIdType extends EntityIdType
{
    public const string NAME = 'position_id';

    protected function idClass(): string
    {
        return PositionId::class;
    }
}
