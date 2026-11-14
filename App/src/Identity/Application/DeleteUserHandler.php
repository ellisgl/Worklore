<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\UserManager;
use App\Identity\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Their sessions, events and projections go with them (ON DELETE CASCADE).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteUserHandler
{
    public function __construct(
        private UserRepository $users,
        private UserManager $accounts,
    ) {
    }

    /**
     * @throws UserNotFound
     */
    public function __invoke(DeleteUser $command): void
    {
        $user = $this->users->find($command->userId)
            ?? throw new UserNotFound();

        $this->accounts->delete($user);
    }
}
