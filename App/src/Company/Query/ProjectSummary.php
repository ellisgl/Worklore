<?php

declare(strict_types=1);

namespace App\Company\Query;

use App\Taxonomy\Query\TagRef;
use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * Read model: one project, shaped for the API (serialized with snake_case
 * keys).
 */
final readonly class ProjectSummary
{
    /**
     * @param list<TagRef> $tags sorted by category, then name
     */
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'uuid')]
        public string $productId,
        #[OA\Property(format: 'uuid')]
        public string $companyId,
        public string $name,
        public ?string $overview,
        public array $tags,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt,
    ) {
    }
}
