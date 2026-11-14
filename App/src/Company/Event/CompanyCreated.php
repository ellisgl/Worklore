<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\CompanyId;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\UserId;
use DateTimeImmutable;

/**
 * A company was added. Stored as "company.created".
 */
final readonly class CompanyCreated implements DomainEvent
{
    public function __construct(
        public CompanyId $companyId,
        public UserId $ownerId,
        public string $name,
        public ?string $overview,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
