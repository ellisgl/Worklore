<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\CompanyId;
use App\Company\ProductId;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\UserId;
use DateTimeImmutable;

/**
 * A product was added to a company. Stored as "product.created".
 */
final readonly class ProductCreated implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public CompanyId $companyId,
        public UserId $ownerId,
        public string $name,
        public ?string $overview,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
