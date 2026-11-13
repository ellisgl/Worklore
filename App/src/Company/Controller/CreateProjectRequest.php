<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Shared\Name;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/products/{productId}/projects body.
 */
#[OA\Schema(required: ['name'])]
final readonly class CreateProjectRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public string $name = '',
        public ?string $overview = null,
    ) {
    }
}
