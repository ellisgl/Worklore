<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\PositionId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The position's overview changed (null: cleared). Stored as "position.overview_changed".
 */
final readonly class PositionOverviewChanged implements DomainEvent
{
    public function __construct(
        public PositionId $positionId,
        public ?string $overview,
    ) {
    }
}
