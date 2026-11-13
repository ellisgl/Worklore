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
    /**
     * @param list<string> $tagIds
     */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public string $name = '',
        public ?string $overview = null,
        #[Assert\All([new Assert\Type('string'), new Assert\Uuid()])]
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'))]
        public array $tagIds = [],
    ) {
    }
}
