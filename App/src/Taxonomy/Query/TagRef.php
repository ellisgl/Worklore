<?php

declare(strict_types=1);

namespace App\Taxonomy\Query;

use OpenApi\Attributes as OA;

/**
 * Read model: a tag on a record (a journal entry, a project), with its
 * category.
 */
final readonly class TagRef
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
