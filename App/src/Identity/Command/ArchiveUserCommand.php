<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\UserManager;
use App\Identity\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

use const DATE_ATOM;

#[AsCommand(name: 'app:user:archive', description: 'Block a user from logging in and end their sessions (keeps their data)')]
final readonly class ArchiveUserCommand
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
            $io->error(sprintf('No user with email "%s".', $email));

            return Command::FAILURE;
        }

        if ($user->isArchived()) {
            $io->note(sprintf('%s was already archived on %s.', $user->email, $user->archivedAt->format(DATE_ATOM)));

            return Command::SUCCESS;
        }

        $revoked = $this->userManager->archive($user);
        $io->success(sprintf('Archived %s and revoked %d session(s).', $user->email, $revoked));

        return Command::SUCCESS;
    }
}
