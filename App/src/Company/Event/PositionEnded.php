<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\PositionId;
use App\Shared\EventSourcing\DomainEvent;
use DateTimeImmutable;

/**
 * The position ended (or its end day or reason was corrected). Stored as "position.ended".
 */
final readonly class PositionEnded implements DomainEvent
{
    public function __construct(
        public PositionId $positionId,
        public DateTimeImmutable $endDate,
        public ?string $reasonLeft,
    ) {
    }
}
