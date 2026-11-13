<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProductId;
use App\Company\ProjectId;
use App\Shared\UserId;

/**
 * Command: start a project for one of the owner's products. The caller
 * picks the id, so dispatching returns nothing.
 */
final readonly class CreateProject
{
    public function __construct(
        public ProjectId $projectId,
        public ProductId $productId,
        public UserId $ownerId,
        public string $name,
        public ?string $overview = null,
    ) {
    }
}
