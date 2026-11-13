<?php

declare(strict_types=1);

namespace App\Journal\Controller;

use App\Shared\Name;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /api/journal-entries/{id} body. Every field is optional: absent or
 * null means unchanged. tag_ids replaces the entry's tags; [] removes them.
 */
final readonly class EditJournalEntryRequest
{
    /**
     * @param list<string>|null $tagIds
     */
    public function __construct(
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public ?string $title = null,
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        public ?string $entry = null,
        #[Assert\All([new Assert\Type('string'), new Assert\Uuid()])]
        #[OA\Property(description: 'Replaces the tags; [] removes them all', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)]
        public ?array $tagIds = null,
    ) {
    }
}
