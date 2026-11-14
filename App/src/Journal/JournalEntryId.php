<?php

declare(strict_types=1);

namespace App\Journal;

use App\Shared\EntityId;

/**
 * Identifies a journal entry (the JournalEntry aggregate and its stream).
 */
final readonly class JournalEntryId extends EntityId
{
}
