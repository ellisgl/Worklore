<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\CompanyId;
use App\Shared\EventSourcing\DomainEvent;
use DateTimeImmutable;

/**
 * The company was archived. Stored as "company.archived".
 */
final readonly class CompanyArchived implements DomainEvent
{
    public function __construct(
        public CompanyId $companyId,
        public DateTimeImmutable $archivedAt,
    ) {
    }
}
