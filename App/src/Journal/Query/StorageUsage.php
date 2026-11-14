<?php

declare(strict_types=1);

namespace App\Journal\Query;

use OpenApi\Attributes as OA;

/**
 * How much media the user stores, and the limits on it.
 */
final readonly class StorageUsage
{
    /**
     * @param list<string> $allowedTypes
     */
    public function __construct(
        #[OA\Property(description: 'Bytes stored', example: 52428800)]
        public int $used,
        #[OA\Property(description: 'Bytes the user may store in total', example: 1073741824)]
        public int $limit,
        #[OA\Property(description: 'Largest single file, in bytes', example: 26214400)]
        public int $maxFileSize,
        #[OA\Property(description: 'Types that may be attached', example: ['image/jpeg', 'application/pdf'])]
        public array $allowedTypes,
    ) {
    }
}
