<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProjectId;
use App\Shared\UserId;

/**
 * Command: unarchive one of the owner's projects.
 * Unarchiving an active project does nothing.
 */
final readonly class UnarchiveProject
{
    public function __construct(
        public ProjectId $projectId,
        public UserId $ownerId,
    ) {
    }
}
