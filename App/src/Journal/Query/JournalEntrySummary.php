<?php

declare(strict_types=1);

namespace App\Journal\Query;

use DateTimeImmutable;

/**
 * Read model: one journal entry with its tags, shaped for the API
 * (serialized with snake_case keys).
 */
final readonly class JournalEntrySummary
{
    /**
     * @param list<JournalTag> $tags sorted by category, then name
     */
    public function __construct(
        public string $id,
        public string $projectId,
        public string $title,
        public string $entry,
        public array $tags,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt,
    ) {
    }
}
