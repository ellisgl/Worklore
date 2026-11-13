<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Company\PositionEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/positions/{id}/end body: {"end_date": "YYYY-MM-DD", "reason_left": "..."}.
 */
final readonly class EndPositionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $endDate = '',
        #[Assert\Length(max: PositionEntity::MAX_REASON_LEFT_LENGTH, normalizer: 'trim')]
        public ?string $reasonLeft = null,
    ) {
    }
}
