<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryId;
use App\Journal\MediaId;
use App\Shared\UserId;

/**
 * Remove a file from a journal entry. Its bytes are deleted from media
 * storage after the change commits (DeleteRemovedMedia).
 */
final readonly class RemoveMedia
{
    public function __construct(
        public JournalEntryId $entryId,
        public UserId $ownerId,
        public MediaId $mediaId,
    ) {
    }
}
