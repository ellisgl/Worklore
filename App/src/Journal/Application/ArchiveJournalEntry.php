<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryId;
use App\Shared\UserId;

/**
 * Command: archive one of the owner's journal entries.
 * Archiving an archived entry keeps its original archive time.
 */
final readonly class ArchiveJournalEntry
{
    public function __construct(
        public JournalEntryId $entryId,
        public UserId $ownerId,
    ) {
    }
}
