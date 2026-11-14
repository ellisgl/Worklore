<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\ProductId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The product's name changed. Stored as "product.renamed".
 */
final readonly class ProductRenamed implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public string $name,
    ) {
    }
}
