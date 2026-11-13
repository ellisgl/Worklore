<?php

declare(strict_types=1);

namespace App\Company\Query;

use DateTimeImmutable;

/**
 * Read model: one product, shaped for the API (serialized with snake_case
 * keys).
 */
final readonly class ProductSummary
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $name,
        public ?string $overview,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt,
        /** Active (non-archived) projects */
        public int $projectCount,
    ) {
    }
}
