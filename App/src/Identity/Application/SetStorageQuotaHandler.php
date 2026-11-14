<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\UserRepository;
use App\Shared\InvalidValueException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A lower quota than a user already uses is allowed: they keep their files
 * but can't add more until they're under it.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class SetStorageQuotaHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @throws InvalidValueException if the quota is negative
     * @throws UserNotFound
     */
    public function __invoke(SetStorageQuota $command): void
    {
        $user = $this->users->find($command->userId)
            ?? throw new UserNotFound();

        $user->setStorageQuota($command->bytes);
    }
}
