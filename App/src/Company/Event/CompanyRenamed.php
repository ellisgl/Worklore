<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\CompanyId;
use App\Shared\EventSourcing\DomainEvent;

/**
 * The company's name changed. Stored as "company.renamed".
 */
final readonly class CompanyRenamed implements DomainEvent
{
    public function __construct(
        public CompanyId $companyId,
        public string $name,
    ) {
    }
}
