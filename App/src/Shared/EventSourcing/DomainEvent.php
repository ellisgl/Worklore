<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

/**
 * Something that happened to an aggregate, in the past tense
 * (CompanyRenamed, PositionEnded). Events are final readonly classes whose
 * promoted constructor parameters are the payload; EventSerializer turns
 * them into JSON and back, so a stored event must stay readable as long as
 * it exists. Change one by adding parameters with defaults, never by
 * renaming or retyping them.
 */
interface DomainEvent
{
}
