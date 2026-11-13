<?php

declare(strict_types=1);

namespace App\Company\Query;

use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * Read model: one company in the owner's list, shaped for the API (serialized
 * with snake_case keys). Not an entity; nothing here can be changed or saved.
 */
final readonly class CompanySummary
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        public string $name,
        public ?string $overview,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt,
        /** Active (non-archived) products */
        public int $productCount,
        /** Title of the newest position the owner currently holds here, if any */
        public ?string $currentPosition,
    ) {
    }
}
