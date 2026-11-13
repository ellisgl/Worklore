<?php

declare(strict_types=1);

namespace App\Journal\Controller;

use App\Journal\Query\JournalFilter;
use App\Taxonomy\TagId;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The ?tag_id=&q= query parameters of the journal entry lists, both optional.
 */
final readonly class JournalFilterRequest
{
    public function __construct(
        #[Assert\Uuid]
        #[OA\Property(description: 'Only entries with this tag', format: 'uuid')]
        public ?string $tagId = null,
        #[Assert\Length(max: 200)]
        #[OA\Property(description: 'Full-text search over title and text: words match their other forms ("deploying" finds "deploy"); use "quoted phrases", or, and -word to exclude')]
        public ?string $q = null,
    ) {
    }

    public function toFilter(): JournalFilter
    {
        return new JournalFilter(null === $this->tagId ? null : TagId::fromString($this->tagId), $this->q);
    }
}
