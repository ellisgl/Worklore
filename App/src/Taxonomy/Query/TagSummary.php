<?php

declare(strict_types=1);

namespace App\Taxonomy\Query;

use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * Read model: one tag with its category's name, shaped for the API
 * (serialized with snake_case keys).
 */
final readonly class TagSummary
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'uuid')]
        public string $categoryId,
        public string $categoryName,
        public string $name,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt,
    ) {
    }
}
