<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Shared\Name;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /api/companies/{id} body. Every field is optional: absent or null
 * means unchanged. An empty overview clears it.
 */
final readonly class EditCompanyRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public ?string $name = null,
        public ?string $overview = null,
    ) {
    }
}
