<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyId;
use App\Shared\UserId;

/**
 * Command: create a company for an owner.
 *
 * The caller picks the id (CompanyId::generate()), so dispatching returns
 * nothing and the caller still knows what was created. Ids, not entities,
 * keep the message plain data.
 */
final readonly class CreateCompany
{
    public function __construct(
        public CompanyId $companyId,
        public UserId $ownerId,
        public string $name,
        public ?string $overview = null,
    ) {
    }
}
