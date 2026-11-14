<?php

declare(strict_types=1);

namespace App\Journal;

use App\Shared\EntityId;
use App\Shared\EventSourcing\InnerId;

/**
 * Identifies a file attached to a journal entry. It is part of the entry's
 * stream, not a stream of its own; its bytes are in media storage.
 */
final readonly class MediaId extends EntityId implements InnerId
{
}
