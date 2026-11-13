<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\UserRepository;
use App\Identity\UserManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:delete', description: 'Delete a user and all of their sessions')]
final readonly class DeleteUserCommand
{
    public function __construct(
        private UserRepository $users,
        private UserManager $userManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the user')] string $email,
        #[Option(description: 'Skip the confirmation prompt')] bool $force = false,
    ): int {
        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            $io->error(\sprintf('No user with email "%s".', $email));

            return Command::FAILURE;
        }

        if (!$force && !$io->confirm(\sprintf('Permanently delete %s?', $user->email), false)) {
            $io->note('Aborted; nothing was deleted. Use --force when running non-interactively.');

            return Command::FAILURE;
        }

        $this->userManager->delete($user);
        $io->success(\sprintf('Deleted user %s.', $email));

        return Command::SUCCESS;
    }
}
