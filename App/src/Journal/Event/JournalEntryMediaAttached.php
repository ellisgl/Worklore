<?php

declare(strict_types=1);

namespace App\Journal\Event;

use App\Journal\JournalEntryId;
use App\Journal\MediaId;
use App\Shared\EventSourcing\DomainEvent;
use DateTimeImmutable;

/**
 * A file was attached to the entry. Stored as "journal_entry.media_attached".
 * Its bytes are in media storage under the owner and media id.
 */
final readonly class JournalEntryMediaAttached implements DomainEvent
{
    public function __construct(
        public JournalEntryId $entryId,
        public MediaId $mediaId,
        public string $filename,
        public string $mediaType,
        public int $size,
        public string $sha256,
        public DateTimeImmutable $attachedAt,
    ) {
    }
}
