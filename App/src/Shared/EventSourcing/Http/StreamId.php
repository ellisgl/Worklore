<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing\Http;

use App\Shared\EntityId;

/**
 * Any stream's id, when the record type is only known as a URL segment.
 */
final readonly class StreamId extends EntityId
{
}
