<?php

declare(strict_types=1);

namespace App\Company\Doctrine;

use App\Company\ProjectId;
use App\Shared\Doctrine\EntityIdType;

final class ProjectIdType extends EntityIdType
{
    public const string NAME = 'project_id';

    protected function idClass(): string
    {
        return ProjectId::class;
    }
}
