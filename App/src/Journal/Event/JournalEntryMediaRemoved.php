<?php

declare(strict_types=1);

namespace App\Journal\Event;

use App\Journal\JournalEntryId;
use App\Journal\MediaId;
use App\Shared\EventSourcing\DomainEvent;
use DateTimeImmutable;

/**
 * A file was removed from the entry. Stored as "journal_entry.media_removed".
 * Its bytes are deleted from media storage once this is saved.
 */
final readonly class JournalEntryMediaRemoved implements DomainEvent
{
    public function __construct(
        public JournalEntryId $entryId,
        public MediaId $mediaId,
        public DateTimeImmutable $removedAt,
    ) {
    }
}
