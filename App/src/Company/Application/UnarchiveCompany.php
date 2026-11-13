<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyId;
use App\Shared\UserId;

/**
 * Command: unarchive one of the owner's companies.
 * Unarchiving an active company does nothing.
 */
final readonly class UnarchiveCompany
{
    public function __construct(
        public CompanyId $companyId,
        public UserId $ownerId,
    ) {
    }
}
