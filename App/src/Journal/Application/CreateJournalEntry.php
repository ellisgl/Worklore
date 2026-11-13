<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Company\ProjectId;
use App\Journal\JournalEntryId;
use App\Shared\UserId;
use App\Taxonomy\TagId;

/**
 * Command: write a journal entry for one of the owner's projects, with any
 * of the owner's tags. The caller picks the id, so dispatching returns
 * nothing.
 */
final readonly class CreateJournalEntry
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
        public array $tagIds = [],
    ) {
    }
}
