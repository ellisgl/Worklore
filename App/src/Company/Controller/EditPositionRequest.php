<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Shared\Name;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /api/positions/{id} body. Every field is optional: absent or null
 * means unchanged. An empty overview clears it; start_date is YYYY-MM-DD.
 */
final readonly class EditPositionRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public ?string $title = null,
        public ?string $overview = null,
        #[Assert\Date]
        #[OA\Property(format: 'date', example: '2024-01-15')]
        public ?string $startDate = null,
    ) {
    }
}
