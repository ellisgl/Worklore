<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * How many bytes of media each user may store in total. Identity answers
 * it (a per-user override, else MEDIA_USER_QUOTA); modules that store files
 * ask it.
 */
interface StorageQuotas
{
    public function limitFor(UserId $userId): int;
}
