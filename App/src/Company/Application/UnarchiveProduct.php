<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProductId;
use App\Shared\UserId;

/**
 * Command: unarchive one of the owner's products.
 * Unarchiving an active product does nothing.
 */
final readonly class UnarchiveProduct
{
    public function __construct(
        public ProductId $productId,
        public UserId $ownerId,
    ) {
    }
}
