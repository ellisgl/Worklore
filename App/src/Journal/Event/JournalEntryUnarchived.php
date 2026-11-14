<?php

declare(strict_types=1);

namespace App\Journal\Event;

use App\Journal\JournalEntryId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The entry was unarchived. Stored as "journal_entry.unarchived".
 */
final readonly class JournalEntryUnarchived implements DomainEvent
{
    public function __construct(
        public JournalEntryId $entryId,
    ) {
    }
}
