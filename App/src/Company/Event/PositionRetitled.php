<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\PositionId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The position's title changed. Stored as "position.retitled".
 */
final readonly class PositionRetitled implements DomainEvent
{
    public function __construct(
        public PositionId $positionId,
        public string $title,
    ) {
    }
}
