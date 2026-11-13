<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Taxonomy\TagId;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The optional ?tag_id= query parameter of the project list.
 */
final readonly class ProjectFilterRequest
{
    public function __construct(
        #[Assert\Uuid]
        #[OA\Property(description: 'Only projects with this tag', format: 'uuid')]
        public ?string $tagId = null,
    ) {
    }

    public function tagId(): ?TagId
    {
        return null === $this->tagId ? null : TagId::fromString($this->tagId);
    }
}
