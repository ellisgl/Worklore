<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyId;
use App\Shared\UserId;

/**
 * Command: change a company's details. Fields left null are unchanged; an
 * empty overview clears it. Applied in one transaction: all or nothing.
 */
final readonly class EditCompany
{
    public function __construct(
        public CompanyId $companyId,
        public UserId $ownerId,
        public ?string $name = null,
        public ?string $overview = null,
    ) {
    }
}
