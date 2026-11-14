<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\PositionId;
use App\Shared\EventSourcing\DomainEvent;
use DateTimeImmutable;

/**
 * The position turned out to start on another day. Stored as "position.start_date_changed".
 */
final readonly class PositionStartDateChanged implements DomainEvent
{
    public function __construct(
        public PositionId $positionId,
        public DateTimeImmutable $startDate,
    ) {
    }
}
