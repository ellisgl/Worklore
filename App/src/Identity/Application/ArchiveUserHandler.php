<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\UserManager;
use App\Identity\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Archiving twice keeps the first time.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveUserHandler
{
    public function __construct(
        private UserRepository $users,
        private UserManager $accounts,
    ) {
    }

    /**
     * @return int the number of sessions ended
     *
     * @throws UserNotFound
     */
    public function __invoke(ArchiveUser $command): int
    {
        $user = $this->users->find($command->userId)
            ?? throw new UserNotFound();

        return $this->accounts->archive($user);
    }
}
