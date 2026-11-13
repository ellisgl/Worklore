<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyId;
use App\Company\ProductId;
use App\Shared\UserId;

/**
 * Command: add a product to one of the owner's companies. The caller picks
 * the id, so dispatching returns nothing.
 */
final readonly class CreateProduct
{
    public function __construct(
        public ProductId $productId,
        public CompanyId $companyId,
        public UserId $ownerId,
        public string $name,
        public ?string $overview = null,
    ) {
    }
}
