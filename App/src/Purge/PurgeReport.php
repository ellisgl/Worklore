<?php

declare(strict_types=1);

namespace App\Purge;

use OpenApi\Attributes as OA;

/**
 * What a purge erased, or (dry run) would erase.
 */
final readonly class PurgeReport
{
    /**
     * @param array<string, int> $purged   records erased, by type
     * @param array<string, int> $untagged records kept that lost a purged tag
     */
    public function __construct(
        #[OA\Property(description: 'True if nothing was changed: this is what would be purged')]
        public bool $dryRun,
        #[OA\Property(description: 'Records erased, by type', type: 'object', example: ['companies' => 1, 'products' => 2, 'projects' => 3, 'positions' => 1, 'journal_entries' => 12, 'categories' => 0, 'tags' => 0], additionalProperties: new OA\AdditionalProperties(type: 'integer'))]
        public array $purged,
        #[OA\Property(description: 'Records kept that lost a purged tag (recorded as ordinary retag events)', type: 'object', example: ['projects' => 0, 'journal_entries' => 0], additionalProperties: new OA\AdditionalProperties(type: 'integer'))]
        public array $untagged,
        #[OA\Property(description: 'Media files deleted with the purged journal entries')]
        public int $files,
    ) {
    }
}
