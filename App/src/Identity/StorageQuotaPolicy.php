<?php

declare(strict_types=1);

namespace App\Identity;

use App\Shared\StorageQuotas;
use App\Shared\UserId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A user's own quota if one was set (app:user:quota), else the default.
 * An unknown user may store nothing.
 */
final readonly class StorageQuotaPolicy implements StorageQuotas
{
    public function __construct(
        private UserRepository $users,
        #[Autowire('%env(bytes:MEDIA_USER_QUOTA)%')]
        private int $defaultQuota,
    ) {
    }

    public function limitFor(UserId $userId): int
    {
        $user = $this->users->find($userId);

        return null === $user ? 0 : $user->storageQuota ?? $this->defaultQuota;
    }

    public function defaultQuota(): int
    {
        return $this->defaultQuota;
    }
}
