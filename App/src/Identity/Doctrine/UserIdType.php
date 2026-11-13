<?php

declare(strict_types=1);

namespace App\Identity\Doctrine;

use App\Identity\UserId;
use App\Shared\Doctrine\EntityIdType;

final class UserIdType extends EntityIdType
{
    public const string NAME = 'user_id';

    protected function idClass(): string
    {
        return UserId::class;
    }
}
