<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use App\Shared\UserId;

final class UserIdType extends EntityIdType
{
    public const string NAME = 'user_id';

    protected function idClass(): string
    {
        return UserId::class;
    }
}
