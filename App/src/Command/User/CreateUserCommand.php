<?php

declare(strict_types=1);

namespace App\Command\User;

use App\User\UserAlreadyExistsException;
use App\User\UserManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsCommand(name: 'app:user:create', description: 'Create a user; the password is prompted for')]
final readonly class CreateUserCommand
{
    public function __construct(private UserManager $userManager)
    {
    }

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'Email address (used to log in)')] string $email,
        #[Option(description: 'Grant ROLE_ADMIN')] bool $admin = false,
    ): int {
        $password = PasswordPrompt::askNew($input, $io);
        if (null === $password) {
            return Command::FAILURE;
        }

        try {
            $user = $this->userManager->create($email, $password, $admin ? ['ROLE_ADMIN'] : []);
        } catch (UserAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (ValidationFailedException $e) {
            PasswordPrompt::printViolations($io, $e);

            return Command::INVALID;
        }

        $io->success(\sprintf('Created user %s (%s) [%s].', $user->email, $user->getId(), implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
