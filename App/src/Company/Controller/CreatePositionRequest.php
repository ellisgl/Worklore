<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Company\PositionEntity;
use App\Shared\Name;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/companies/{companyId}/positions body. Dates are YYYY-MM-DD; an
 * end_date records the position as already ended.
 */
#[OA\Schema(required: ['title', 'start_date'])]
final readonly class CreatePositionRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public string $title = '',
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(format: 'date', example: '2024-01-15')]
        public string $startDate = '',
        public ?string $overview = null,
        #[Assert\Date]
        #[OA\Property(format: 'date', example: '2024-01-15')]
        public ?string $endDate = null,
        #[Assert\Length(max: PositionEntity::MAX_REASON_LEFT_LENGTH, normalizer: 'trim')]
        public ?string $reasonLeft = null,
    ) {
    }
}
