<?php

declare(strict_types=1);

namespace App\Taxonomy\Controller;

use App\Shared\Name;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /api/tags/{id} body. Every field is optional: absent or null means
 * unchanged. A category_id moves the tag to that category.
 */
final readonly class EditTagRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public ?string $name = null,
        #[Assert\Uuid]
        #[OA\Property(description: 'Move the tag to this category', format: 'uuid')]
        public ?string $categoryId = null,
    ) {
    }
}
