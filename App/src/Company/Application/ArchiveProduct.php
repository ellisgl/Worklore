<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProductId;
use App\Shared\UserId;

/**
 * Command: archive one of the owner's products.
 * Archiving an archived product keeps its original archive time.
 */
final readonly class ArchiveProduct
{
    public function __construct(
        public ProductId $productId,
        public UserId $ownerId,
    ) {
    }
}
