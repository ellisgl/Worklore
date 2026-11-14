<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Shared\UserId;

/**
 * Restore an export into $ownerId's account by replaying its events. The
 * account must have no records yet.
 */
final readonly class ImportData
{
    /**
     * @param list<ExportedEvent> $events oldest first, as exported
     */
    public function __construct(
        public UserId $ownerId,
        public array $events,
    ) {
    }
}
