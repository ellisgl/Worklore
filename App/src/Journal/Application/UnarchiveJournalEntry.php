<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryId;
use App\Shared\UserId;

/**
 * Command: unarchive one of the owner's journal entries.
 * Unarchiving an active entry does nothing.
 */
final readonly class UnarchiveJournalEntry
{
    public function __construct(
        public JournalEntryId $entryId,
        public UserId $ownerId,
    ) {
    }
}
