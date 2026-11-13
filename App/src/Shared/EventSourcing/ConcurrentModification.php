<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use App\Shared\ConflictException;

/**
 * Another request changed the record after this one loaded it; the change
 * is refused rather than built on stale state (409, try again).
 */
final class ConcurrentModification extends ConflictException
{
    public function __construct()
    {
        parent::__construct('This record was changed by another request; reload it and try again.');
    }
}
