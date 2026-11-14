<?php

declare(strict_types=1);

namespace App\Journal\Event;

use App\Journal\JournalEntryId;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\EventSourcing\ListOf;
use App\Taxonomy\TagId;

/**
 * The entry's tags were replaced (sorted by id; may be empty). Stored as "journal_entry.retagged".
 */
final readonly class JournalEntryRetagged implements DomainEvent
{
    /**
     * @param list<TagId> $tagIds
     */
    public function __construct(
        public JournalEntryId $entryId,
        #[ListOf(TagId::class)]
        public array $tagIds,
    ) {
    }
}
