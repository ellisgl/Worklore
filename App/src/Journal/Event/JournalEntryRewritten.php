<?php

declare(strict_types=1);

namespace App\Journal\Event;

use App\Journal\JournalEntryId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The entry's text changed. Stored as "journal_entry.rewritten".
 */
final readonly class JournalEntryRewritten implements DomainEvent
{
    public function __construct(
        public JournalEntryId $entryId,
        public string $entry,
    ) {
    }
}
