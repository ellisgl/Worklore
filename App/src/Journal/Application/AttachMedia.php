<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryId;
use App\Journal\MediaId;
use App\Shared\UserId;

/**
 * Attach the file at $path (an upload, already checked against MediaRules)
 * to a journal entry. The handler copies it into media storage.
 */
final readonly class AttachMedia
{
    public function __construct(
        public JournalEntryId $entryId,
        public UserId $ownerId,
        public MediaId $mediaId,
        public string $path,
        public string $filename,
        public string $mediaType,
        public int $size,
        public string $sha256,
    ) {
    }
}
