<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Application\UnarchiveUser;
use App\Identity\UserRepository;
use App\Shared\CommandBus;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Throwable;

use function sprintf;

#[AsCommand(name: 'app:user:unarchive', description: 'Let an archived user log in again')]
final readonly class UnarchiveUserCommand
{
    public function __construct(
        private UserRepository $users,
        private CommandBus $commands,
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws Throwable
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the user')] string $email,
    ): int {
        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            $io->error(sprintf('No user with email "%s".', $email));

            return Command::FAILURE;
        }

        if (!$user->isArchived()) {
            $io->note(sprintf('%s is not archived.', $user->email));

            return Command::SUCCESS;
        }

        $this->commands->dispatch(new UnarchiveUser($user->id));
        $io->success(sprintf('Unarchived %s; they can log in again.', $user->email));

        return Command::SUCCESS;
    }
}
