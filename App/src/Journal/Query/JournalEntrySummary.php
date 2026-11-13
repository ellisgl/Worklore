<?php

declare(strict_types=1);

namespace App\Journal\Query;

use App\Taxonomy\Query\TagRef;
use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * Read model: one journal entry with its tags, shaped for the API
 * (serialized with snake_case keys).
 */
final readonly class JournalEntrySummary
{
    /**
     * @param list<TagRef> $tags sorted by category, then name
     */
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'uuid')]
        public string $projectId,
        public string $projectName,
        public string $title,
        public string $entry,
        public array $tags,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt,
    ) {
    }
}
