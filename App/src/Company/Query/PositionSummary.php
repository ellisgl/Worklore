<?php

declare(strict_types=1);

namespace App\Company\Query;

use DateTimeImmutable;

/**
 * Read model: one position, shaped for the API (serialized with snake_case
 * keys). Dates are YYYY-MM-DD strings, as stored.
 */
final readonly class PositionSummary
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $title,
        public ?string $overview,
        public string $startDate,
        public ?string $endDate,
        /** No end date yet */
        public bool $current,
        public ?string $reasonLeft,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt,
    ) {
    }
}
