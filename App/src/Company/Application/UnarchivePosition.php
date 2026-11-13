<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\PositionId;
use App\Shared\UserId;

/**
 * Command: unarchive one of the owner's positions.
 * Unarchiving an active position does nothing.
 */
final readonly class UnarchivePosition
{
    public function __construct(
        public PositionId $positionId,
        public UserId $ownerId,
    ) {
    }
}
