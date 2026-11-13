<?php

declare(strict_types=1);

namespace App\Journal\Controller;

use App\Shared\Name;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/projects/{projectId}/journal-entries body.
 */
final readonly class CreateJournalEntryRequest
{
    /**
     * @param list<string> $tagIds
     */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Name::MAX_LENGTH, normalizer: 'trim')]
        public string $title = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        public string $entry = '',
        #[Assert\All([new Assert\Type('string'), new Assert\Uuid()])]
        public array $tagIds = [],
    ) {
    }
}
