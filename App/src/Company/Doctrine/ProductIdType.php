<?php

declare(strict_types=1);

namespace App\Company\Doctrine;

use App\Company\ProductId;
use App\Shared\Doctrine\EntityIdType;

final class ProductIdType extends EntityIdType
{
    public const string NAME = 'product_id';

    protected function idClass(): string
    {
        return ProductId::class;
    }
}
