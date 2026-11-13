<?php

declare(strict_types=1);

namespace App\Company\Query;

use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * Read model: one position, shaped for the API (serialized with snake_case
 * keys). Dates are YYYY-MM-DD strings, as stored.
 */
final readonly class PositionSummary
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'uuid')]
        public string $companyId,
        public string $title,
        public ?string $overview,
        #[OA\Property(format: 'date', example: '2024-01-15')]
        public string $startDate,
        #[OA\Property(format: 'date', example: '2024-01-15')]
        public ?string $endDate,
        /** No end date yet */
        public bool $current,
        public ?string $reasonLeft,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt,
    ) {
    }
}
