<?php

declare(strict_types=1);

namespace App\Identity\Doctrine;

use App\Identity\ApiTokenId;
use App\Shared\Doctrine\EntityIdType;

final class ApiTokenIdType extends EntityIdType
{
    public const string NAME = 'api_token_id';

    protected function idClass(): string
    {
        return ApiTokenId::class;
    }
}
