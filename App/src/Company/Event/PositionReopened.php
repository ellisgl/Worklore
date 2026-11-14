<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\PositionId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The position is current again; its end day and reason are gone. Stored as "position.reopened".
 */
final readonly class PositionReopened implements DomainEvent
{
    public function __construct(
        public PositionId $positionId,
    ) {
    }
}
