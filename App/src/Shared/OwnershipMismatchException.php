<?php

declare(strict_types=1);

namespace App\Shared;

use InvalidArgumentException;

use function sprintf;

/**
 * Thrown when records owned by different users would be linked, e.g. a
 * product under someone else's company, or someone else's tag on a project.
 *
 * Endpoints should treat it like a lookup miss (404), so it doesn't reveal
 * that the other user's record exists.
 */
final class OwnershipMismatchException extends InvalidArgumentException
{
    /**
     * @param string $what what $owner owns, for the message ("company", "tag")
     *
     * @throws self if $owner and $other are different owners
     */
    public static function assertSameOwner(Owner $owner, Owner $other, string $what): void
    {
        // Compare ids, not objects: a lazy reference and a loaded entity can
        // be the same owner.
        if (!$owner->ownerId()->equals($other->ownerId())) {
            throw new self(sprintf('The %s belongs to a different user', $what));
        }
    }
}
