<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\PositionId;
use App\Shared\UserId;
use DateTimeImmutable;

/**
 * Command: mark a position as ended. Ending an ended position corrects its
 * end date and reason.
 */
final readonly class EndPosition
{
    public function __construct(
        public PositionId $positionId,
        public UserId $ownerId,
        public DateTimeImmutable $endDate,
        public ?string $reasonLeft = null,
    ) {
    }
}
