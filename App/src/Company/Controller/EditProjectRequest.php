<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Shared\Name;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /api/projects/{id} body. Every field is optional: absent or null
 * means unchanged. An empty overview clears it; tag_ids replaces the tags.
 */
final readonly class EditProjectRequest
{
    /**
     * @param list<string>|null $tagIds
     */
    public function __construct(
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public ?string $name = null,
        public ?string $overview = null,
        #[Assert\All([new Assert\Type('string'), new Assert\Uuid()])]
        #[OA\Property(description: 'Replaces the tags; [] removes them all', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)]
        public ?array $tagIds = null,
    ) {
    }
}
