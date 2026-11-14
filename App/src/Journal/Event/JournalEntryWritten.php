<?php

declare(strict_types=1);

namespace App\Journal\Event;

use App\Company\ProjectId;
use App\Journal\JournalEntryId;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\EventSourcing\ListOf;
use App\Shared\UserId;
use App\Taxonomy\TagId;
use DateTimeImmutable;

/**
 * A journal entry was written for a project. Stored as "journal_entry.written".
 */
final readonly class JournalEntryWritten implements DomainEvent
{
    /**
     * @param list<TagId> $tagIds
     */
    public function __construct(
        public JournalEntryId $entryId,
        public ProjectId $projectId,
        public UserId $ownerId,
        public string $title,
        public string $entry,
        #[ListOf(TagId::class)]
        public array $tagIds,
        public DateTimeImmutable $writtenAt,
    ) {
    }
}
