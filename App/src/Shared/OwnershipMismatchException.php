<?php

declare(strict_types=1);

namespace App\Shared;

use App\Identity\User;
use InvalidArgumentException;

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
     * @throws self if $owner and $user are different users
     */
    public static function assertSameUser(User $owner, User $user, string $what): void
    {
        // Compare ids, not objects: a lazy reference and a loaded entity can
        // be the same user.
        if (!$owner->id->equals($user->id)) {
            throw new self(\sprintf('The %s belongs to a different user', $what));
        }
    }
}
