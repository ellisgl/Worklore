<?php

declare(strict_types=1);

namespace App\Export;

use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * Everything a user owns, archived records included, as one JSON document:
 * a flat list per record type, linked by ids (company_id, tag_ids, ...),
 * then "events", the full history those records were built from. Serialized
 * with snake_case keys; the rows already use them.
 *
 * The events are the lossless part: importing an export replays them, and
 * ignores the record lists (a readable snapshot of the same data).
 */
final readonly class DataExport
{
    public const string FORMAT = 'worklore-export';
    public const int VERSION = 2;

    public string $format;
    #[OA\Property(description: 'Bumped when the layout changes incompatibly')]
    public int $version;

    /**
     * @param array<string, mixed>       $account
     * @param list<array<string, mixed>> $companies
     * @param list<array<string, mixed>> $products
     * @param list<array<string, mixed>> $projects       with tag_ids
     * @param list<array<string, mixed>> $positions
     * @param list<array<string, mixed>> $categories
     * @param list<array<string, mixed>> $tags
     * @param list<array<string, mixed>> $journalEntries with tag_ids
     * @param list<array<string, mixed>> $events         oldest first: stream_type, stream_id, version, type, recorded_at, data
     */
    public function __construct(
        public DateTimeImmutable $exportedAt,
        #[OA\Property(type: 'object')]
        public array $account,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object'))]
        public array $companies,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object'))]
        public array $products,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object'))]
        public array $projects,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object'))]
        public array $positions,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object'))]
        public array $categories,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object'))]
        public array $tags,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object'))]
        public array $journalEntries,
        #[OA\Property(
            description: 'Every change to every record, oldest first. Each has the record (stream_type, stream_id), its position in that record\'s history (version, from 1), what happened (type), when (recorded_at) and the event as stored (data).',
            type: 'array',
            items: new OA\Items(type: 'object'),
        )]
        public array $events,
    ) {
        $this->format = self::FORMAT;
        $this->version = self::VERSION;
    }
}
