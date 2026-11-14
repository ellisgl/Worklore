<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * Read model: one event in a record's history.
 */
final readonly class HistoryEntry
{
    /**
     * @param array<string, mixed> $data the event as stored (snake_case keys)
     */
    public function __construct(
        #[OA\Property(description: 'Position in the record\'s history, from 1', example: 1)]
        public int $version,
        #[OA\Property(description: 'What happened, e.g. company.renamed', example: 'company.renamed')]
        public string $type,
        public DateTimeImmutable $recordedAt,
        #[OA\Property(type: 'object', additionalProperties: true)]
        public array $data,
        #[OA\Property(description: 'The admin who made this change while impersonating you, if one did', format: 'uuid')]
        public ?string $impersonatedBy = null,
    ) {
    }
}
