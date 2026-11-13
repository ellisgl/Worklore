<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyId;
use App\Shared\UserId;

/**
 * Command: archive one of the owner's companies.
 * Archiving an archived company keeps its original archive time.
 */
final readonly class ArchiveCompany
{
    public function __construct(
        public CompanyId $companyId,
        public UserId $ownerId,
    ) {
    }
}
