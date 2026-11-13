<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProjectId;
use App\Shared\UserId;

/**
 * Command: archive one of the owner's projects.
 * Archiving an archived project keeps its original archive time.
 */
final readonly class ArchiveProject
{
    public function __construct(
        public ProjectId $projectId,
        public UserId $ownerId,
    ) {
    }
}
