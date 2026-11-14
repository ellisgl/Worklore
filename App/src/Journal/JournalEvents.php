<?php

declare(strict_types=1);

namespace App\Journal;

use App\Journal\Event\JournalEntryArchived;
use App\Journal\Event\JournalEntryMediaAttached;
use App\Journal\Event\JournalEntryMediaRemoved;
use App\Journal\Event\JournalEntryRetagged;
use App\Journal\Event\JournalEntryRetitled;
use App\Journal\Event\JournalEntryRewritten;
use App\Journal\Event\JournalEntryUnarchived;
use App\Journal\Event\JournalEntryWritten;
use App\Shared\EventSourcing\EventCatalog;

/**
 * The Journal module's events and their stored names. Never rename these.
 */
final class JournalEvents implements EventCatalog
{
    public const string STREAM = 'journal_entry';

    public function events(): array
    {
        return [
            'journal_entry.written' => JournalEntryWritten::class,
            'journal_entry.retitled' => JournalEntryRetitled::class,
            'journal_entry.rewritten' => JournalEntryRewritten::class,
            'journal_entry.retagged' => JournalEntryRetagged::class,
            'journal_entry.archived' => JournalEntryArchived::class,
            'journal_entry.unarchived' => JournalEntryUnarchived::class,
            'journal_entry.media_attached' => JournalEntryMediaAttached::class,
            'journal_entry.media_removed' => JournalEntryMediaRemoved::class,
        ];
    }
}
