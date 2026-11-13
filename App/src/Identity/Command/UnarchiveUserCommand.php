<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\UserRepository;
use App\Identity\UserManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:unarchive', description: 'Let an archived user log in again')]
final readonly class UnarchiveUserCommand
{
    public function __construct(
        private UserRepository $users,
        private UserManager $userManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the user')] string $email,
    ): int {
        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            $io->error(\sprintf('No user with email "%s".', $email));

            return Command::FAILURE;
        }

        if (!$user->isArchived()) {
            $io->note(\sprintf('%s is not archived.', $user->email));

            return Command::SUCCESS;
        }

        $this->userManager->unarchive($user);
        $io->success(\sprintf('Unarchived %s; they can log in again.', $user->email));

        return Command::SUCCESS;
    }
}
