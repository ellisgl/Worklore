<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProjectId;
use App\Shared\UserId;
use App\Taxonomy\TagId;

/**
 * Command: change a project's details. Fields left null are unchanged; an
 * empty overview clears it, and a tag list replaces the tags ([] removes
 * them all). Applied in one transaction.
 */
final readonly class EditProject
{
    /**
     * @param list<TagId>|null $tagIds
     */
    public function __construct(
        public ProjectId $projectId,
        public UserId $ownerId,
        public ?string $name = null,
        public ?string $overview = null,
        public ?array $tagIds = null,
    ) {
    }
}
