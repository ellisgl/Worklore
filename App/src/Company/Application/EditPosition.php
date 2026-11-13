<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\PositionId;
use App\Shared\UserId;
use DateTimeImmutable;

/**
 * Command: change a position's details. Fields left null are unchanged; an
 * empty overview clears it. Applied in one transaction. Ending and
 * reopening are their own commands.
 */
final readonly class EditPosition
{
    public function __construct(
        public PositionId $positionId,
        public UserId $ownerId,
        public ?string $title = null,
        public ?string $overview = null,
        public ?DateTimeImmutable $startDate = null,
    ) {
    }
}
