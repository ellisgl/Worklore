<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\PositionId;
use App\Shared\UserId;

/**
 * Command: archive one of the owner's positions.
 * Archiving an archived position keeps its original archive time.
 */
final readonly class ArchivePosition
{
    public function __construct(
        public PositionId $positionId,
        public UserId $ownerId,
    ) {
    }
}
