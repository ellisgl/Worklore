<?php

declare(strict_types=1);

namespace App\Company\Doctrine;

use App\Company\CompanyId;
use App\Shared\Doctrine\EntityIdType;

final class CompanyIdType extends EntityIdType
{
    public const string NAME = 'company_id';

    protected function idClass(): string
    {
        return CompanyId::class;
    }
}
