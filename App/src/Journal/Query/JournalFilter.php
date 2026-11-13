<?php

declare(strict_types=1);

namespace App\Journal\Query;

use App\Taxonomy\TagId;

use function trim;

/**
 * Narrows a journal entry list: entries with this tag, and/or matching a
 * full-text search over title and text. Empty means no filter.
 */
final readonly class JournalFilter
{
    /** The search text, trimmed; null when blank */
    public ?string $search;

    public function __construct(
        public ?TagId $tagId = null,
        ?string $search = null,
    ) {
        $search = null === $search ? '' : trim($search);
        $this->search = '' === $search ? null : $search;
    }
}
