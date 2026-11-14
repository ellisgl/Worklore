<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\UserManager;
use App\Identity\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Unarchiving an active user does nothing.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveUserHandler
{
    public function __construct(
        private UserRepository $users,
        private UserManager $accounts,
    ) {
    }

    /**
     * @throws UserNotFound
     */
    public function __invoke(UnarchiveUser $command): void
    {
        $user = $this->users->find($command->userId)
            ?? throw new UserNotFound();

        $this->accounts->unarchive($user);
    }
}
