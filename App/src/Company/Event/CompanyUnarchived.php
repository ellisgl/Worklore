<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\CompanyId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The company was unarchived. Stored as "company.unarchived".
 */
final readonly class CompanyUnarchived implements DomainEvent
{
    public function __construct(
        public CompanyId $companyId,
    ) {
    }
}
