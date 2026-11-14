<?php

declare(strict_types=1);

namespace App\Purge\Application;

use App\Shared\UserId;

/**
 * Published on event.bus after a purge commits: these files' bytes go next.
 */
final readonly class MediaPurged
{
    /**
     * @param list<string> $mediaIds
     */
    public function __construct(
        public UserId $ownerId,
        public array $mediaIds,
    ) {
    }
}
