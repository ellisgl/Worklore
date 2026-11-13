<?php

declare(strict_types=1);

namespace App\Taxonomy\Controller;

use App\Shared\Name;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/categories body.
 */
final readonly class CreateCategoryRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public string $name = '',
        public ?string $description = null,
    ) {
    }
}
