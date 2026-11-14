<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Security\TokenManager;
use App\Identity\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Log out everywhere.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RevokeSessionsHandler
{
    public function __construct(
        private UserRepository $users,
        private TokenManager $tokens,
    ) {
    }

    /**
     * @return int the number of sessions ended
     *
     * @throws UserNotFound
     */
    public function __invoke(RevokeSessions $command): int
    {
        $user = $this->users->find($command->userId)
            ?? throw new UserNotFound();

        return $this->tokens->revokeAllForUser($user);
    }
}
