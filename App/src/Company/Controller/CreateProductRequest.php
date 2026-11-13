<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Shared\Name;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/companies/{companyId}/products body.
 */
final readonly class CreateProductRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public string $name = '',
        public ?string $overview = null,
    ) {
    }
}
