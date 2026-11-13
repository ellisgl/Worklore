<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyId;
use App\Company\PositionId;
use App\Shared\UserId;
use DateTimeImmutable;

/**
 * Command: record a position the owner held (or holds) at one of their
 * companies. With an end date it's recorded as already ended. The caller
 * picks the id, so dispatching returns nothing.
 */
final readonly class CreatePosition
{
    public function __construct(
        public PositionId $positionId,
        public CompanyId $companyId,
        public UserId $ownerId,
        public string $title,
        public DateTimeImmutable $startDate,
        public ?string $overview = null,
        public ?DateTimeImmutable $endDate = null,
        public ?string $reasonLeft = null,
    ) {
    }
}
