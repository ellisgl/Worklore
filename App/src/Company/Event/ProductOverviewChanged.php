<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\ProductId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The product's overview changed (null: cleared). Stored as "product.overview_changed".
 */
final readonly class ProductOverviewChanged implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public ?string $overview,
    ) {
    }
}
