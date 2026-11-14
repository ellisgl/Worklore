<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Shared\UserId;

/**
 * Restore an export into $ownerId's account by replaying its events. The
 * account must have no records yet. Media files come from $files (an
 * archive export); without it, an export with media is refused.
 */
final readonly class ImportData
{
    /**
     * @param list<ExportedEvent> $events oldest first, as exported
     */
    public function __construct(
        public UserId $ownerId,
        public array $events,
        public ?ImportedFiles $files = null,
    ) {
    }
}
