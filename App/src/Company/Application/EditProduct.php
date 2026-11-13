<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProductId;
use App\Shared\UserId;

/**
 * Command: change a product's details. Fields left null are unchanged; an
 * empty overview clears it. Applied in one transaction.
 */
final readonly class EditProduct
{
    public function __construct(
        public ProductId $productId,
        public UserId $ownerId,
        public ?string $name = null,
        public ?string $overview = null,
    ) {
    }
}
