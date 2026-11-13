<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Shared\Name;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/companies body. Validated before the command is dispatched, so
 * clients get field-level violations; the domain rules still apply after.
 */
#[OA\Schema(required: ['name'])]
final readonly class CreateCompanyRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public string $name = '',
        public ?string $overview = null,
    ) {
    }
}
