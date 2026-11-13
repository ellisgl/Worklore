<?php

declare(strict_types=1);

namespace App\Taxonomy\Query;

use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * Read model: one category, shaped for the API (serialized with snake_case
 * keys).
 */
final readonly class CategorySummary
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        public string $name,
        public ?string $description,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt,
        /** Active (unarchived) tags */
        public int $tagCount,
    ) {
    }
}
