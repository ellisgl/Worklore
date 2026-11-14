<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\PositionId;
use App\Shared\EventSourcing\DomainEvent;
use DateTimeImmutable;

/**
 * The position was archived. Stored as "position.archived".
 */
final readonly class PositionArchived implements DomainEvent
{
    public function __construct(
        public PositionId $positionId,
        public DateTimeImmutable $archivedAt,
    ) {
    }
}
