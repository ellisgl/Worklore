<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\ProductId;
use App\Shared\EventSourcing\DomainEvent;
use DateTimeImmutable;

/**
 * The product was archived. Stored as "product.archived".
 */
final readonly class ProductArchived implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public DateTimeImmutable $archivedAt,
    ) {
    }
}
