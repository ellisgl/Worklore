<?php

declare(strict_types=1);

namespace App\Export\Application;

use OpenApi\Attributes as OA;

/**
 * What an import restored.
 */
final readonly class ImportResult
{
    public function __construct(
        #[OA\Property(description: 'Records restored: companies, products, tags, journal entries, ...', example: 12)]
        public int $records,
        #[OA\Property(description: 'Events replayed: every change those records went through', example: 31)]
        public int $events,
        #[OA\Property(description: 'Media files restored', example: 3)]
        public int $files = 0,
    ) {
    }
}
