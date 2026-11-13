<?php

declare(strict_types=1);

namespace App\Company\Query;

use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * Read model: one product, shaped for the API (serialized with snake_case
 * keys).
 */
final readonly class ProductSummary
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'uuid')]
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
