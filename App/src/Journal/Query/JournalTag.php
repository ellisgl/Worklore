<?php

declare(strict_types=1);

namespace App\Journal\Query;

use OpenApi\Attributes as OA;

/**
 * Read model: a tag on a journal entry, with its category.
 */
final readonly class JournalTag
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        public string $name,
        #[OA\Property(format: 'uuid')]
        public string $categoryId,
        public string $categoryName,
    ) {
    }
}
