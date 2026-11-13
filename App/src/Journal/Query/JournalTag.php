<?php

declare(strict_types=1);

namespace App\Journal\Query;

/**
 * Read model: a tag on a journal entry, with its category.
 */
final readonly class JournalTag
{
    public function __construct(
        public string $id,
        public string $name,
        public string $categoryId,
        public string $categoryName,
    ) {
    }
}
