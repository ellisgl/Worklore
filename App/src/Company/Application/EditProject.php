<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProjectId;
use App\Shared\UserId;

/**
 * Command: change a project's details. Fields left null are unchanged; an
 * empty overview clears it. Applied in one transaction.
 */
final readonly class EditProject
{
    public function __construct(
        public ProjectId $projectId,
        public UserId $ownerId,
        public ?string $name = null,
        public ?string $overview = null,
    ) {
    }
}
