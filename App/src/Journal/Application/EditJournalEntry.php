<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryId;
use App\Shared\UserId;
use App\Taxonomy\TagId;

/**
 * Command: change a journal entry. Fields left null are unchanged; a tag
 * list replaces the entry's tags (an empty list removes them all).
 * Applied in one transaction.
 */
final readonly class EditJournalEntry
{
    /**
     * @param list<TagId>|null $tagIds
     */
    public function __construct(
        public JournalEntryId $entryId,
        public UserId $ownerId,
        public ?string $title = null,
        public ?string $entry = null,
        public ?array $tagIds = null,
    ) {
    }
}
