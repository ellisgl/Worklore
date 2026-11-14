<?php

declare(strict_types=1);

namespace App\Journal\Query;

use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * A file attached to a journal entry. Download it from
 * GET /api/journal-entries/{entry id}/media/{id}.
 */
final readonly class MediaSummary
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(example: 'whiteboard.jpg')]
        public string $filename,
        #[OA\Property(description: 'Detected from the file\'s content', example: 'image/jpeg')]
        public string $mediaType,
        #[OA\Property(description: 'Bytes', example: 482113)]
        public int $size,
        #[OA\Property(description: 'SHA-256 of the content, hex')]
        public string $sha256,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
