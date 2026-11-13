<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Company\PositionEntity;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/positions/{id}/end body: {"end_date": "YYYY-MM-DD", "reason_left": "..."}.
 */
#[OA\Schema(required: ['end_date'])]
final readonly class EndPositionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(format: 'date', example: '2024-01-15')]
        public string $endDate = '',
        #[Assert\Length(max: PositionEntity::MAX_REASON_LEFT_LENGTH, normalizer: 'trim')]
        public ?string $reasonLeft = null,
    ) {
    }
}
