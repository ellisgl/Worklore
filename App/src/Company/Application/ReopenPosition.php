<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\PositionId;
use App\Shared\UserId;

/**
 * Command: make an ended position current again (clears the end date and
 * the reason). Reopening a current position does nothing.
 */
final readonly class ReopenPosition
{
    public function __construct(
        public PositionId $positionId,
        public UserId $ownerId,
    ) {
    }
}
