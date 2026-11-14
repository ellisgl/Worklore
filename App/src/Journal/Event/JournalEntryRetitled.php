<?php

declare(strict_types=1);

namespace App\Journal\Event;

use App\Journal\JournalEntryId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The entry's title changed. Stored as "journal_entry.retitled".
 */
final readonly class JournalEntryRetitled implements DomainEvent
{
    public function __construct(
        public JournalEntryId $entryId,
        public string $title,
    ) {
    }
}
