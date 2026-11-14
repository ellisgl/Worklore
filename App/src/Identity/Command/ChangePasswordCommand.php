<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Application\ChangePassword;
use App\Identity\UserRepository;
use App\Shared\CommandBus;
use App\Shared\Secret;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

use function is_int;
use function sprintf;

#[AsCommand(name: 'app:user:change-password', description: "Set a user's password and revoke their sessions")]
final readonly class ChangePasswordCommand
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
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the user')] string $email,
    ): int {
        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            $io->error(sprintf('No user with email "%s".', $email));

            return Command::FAILURE;
        }

        $password = PasswordPrompt::askNew($input, $io);
        if (null === $password) {
            return Command::FAILURE;
        }

        try {
            $revoked = $this->commands->dispatch(new ChangePassword($user->id, new Secret($password)));
        } catch (ValidationFailedException $e) {
            PasswordPrompt::printViolations($io, $e);

            return Command::INVALID;
        }

        $io->success(sprintf('Password changed for %s; %d session(s) revoked.', $user->email, is_int($revoked) ? $revoked : 0));

        return Command::SUCCESS;
    }
}
