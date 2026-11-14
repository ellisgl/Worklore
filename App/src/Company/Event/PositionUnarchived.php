<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\PositionId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The position was unarchived. Stored as "position.unarchived".
 */
final readonly class PositionUnarchived implements DomainEvent
{
    public function __construct(
        public PositionId $positionId,
    ) {
    }
}
