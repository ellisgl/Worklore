<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\CompanyId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The company's overview changed (null: cleared). Stored as "company.overview_changed".
 */
final readonly class CompanyOverviewChanged implements DomainEvent
{
    public function __construct(
        public CompanyId $companyId,
        public ?string $overview,
    ) {
    }
}
