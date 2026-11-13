<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Repository\UserRepository;
use App\User\UserManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsCommand(name: 'app:user:change-password', description: "Set a user's password and revoke their sessions")]
final readonly class ChangePasswordCommand
{
    public function __construct(
        private UserRepository $users,
        private UserManager $userManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the user')] string $email,
    ): int {
        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            $io->error(\sprintf('No user with email "%s".', $email));

            return Command::FAILURE;
        }

        $password = PasswordPrompt::askNew($input, $io);
        if (null === $password) {
            return Command::FAILURE;
        }

        try {
            $revoked = $this->userManager->changePassword($user, $password);
        } catch (ValidationFailedException $e) {
            PasswordPrompt::printViolations($io, $e);

            return Command::INVALID;
        }

        $io->success(\sprintf('Password changed for %s; %d session(s) revoked.', $user->email, $revoked));

        return Command::SUCCESS;
    }
}
