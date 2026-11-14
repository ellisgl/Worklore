<?php

declare(strict_types=1);

namespace App\Journal\Event;

use App\Journal\JournalEntryId;
use App\Shared\EventSourcing\DomainEvent;
use DateTimeImmutable;

/**
 * The entry was archived. Stored as "journal_entry.archived".
 */
final readonly class JournalEntryArchived implements DomainEvent
{
    public function __construct(
        public JournalEntryId $entryId,
        public DateTimeImmutable $archivedAt,
    ) {
    }
}
