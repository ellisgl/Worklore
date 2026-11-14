<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\ProductId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The product was unarchived. Stored as "product.unarchived".
 */
final readonly class ProductUnarchived implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
    ) {
    }
}
