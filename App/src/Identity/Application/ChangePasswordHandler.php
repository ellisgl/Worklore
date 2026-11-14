<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\IncorrectPassword;
use App\Identity\UserManager;
use App\Identity\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Every session ends, so the old password stops working everywhere.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ChangePasswordHandler
{
    public function __construct(
        private UserRepository $users,
        private UserManager $accounts,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    /**
     * @return int the number of sessions ended
     *
     * @throws IncorrectPassword
     * @throws UserNotFound
     * @throws ValidationFailedException if the new password breaks the rules
     */
    public function __invoke(ChangePassword $command): int
    {
        $user = $this->users->find($command->userId)
            ?? throw new UserNotFound();

        if (null !== $command->currentPassword && !$this->hasher->isPasswordValid($user, $command->currentPassword->reveal())) {
            throw new IncorrectPassword();
        }

        return $this->accounts->changePassword($user, $command->newPassword->reveal());
    }
}
