<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Published on event.bus once a user is deleted. Their rows went with them
 * (ON DELETE CASCADE); listeners clean up what lives elsewhere, such as
 * stored files.
 */
final readonly class UserDeleted
{
    public function __construct(public UserId $userId)
    {
    }
}
