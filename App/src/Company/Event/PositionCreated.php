<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\CompanyId;
use App\Company\PositionId;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\UserId;
use DateTimeImmutable;

/**
 * A position held at a company was recorded, starting on a day. Stored as "position.created".
 */
final readonly class PositionCreated implements DomainEvent
{
    public function __construct(
        public PositionId $positionId,
        public CompanyId $companyId,
        public UserId $ownerId,
        public string $title,
        public ?string $overview,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
