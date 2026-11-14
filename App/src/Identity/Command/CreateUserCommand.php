<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Application\RegisterUser;
use App\Identity\UserAlreadyExistsException;
use App\Identity\UserRepository;
use App\Shared\CommandBus;
use App\Shared\Secret;
use App\Shared\UserId;
use LogicException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

use function sprintf;

#[AsCommand(name: 'app:user:create', description: 'Create a user; the password is prompted for')]
final readonly class CreateUserCommand
{
    public function __construct(
        private CommandBus $commands,
        private UserRepository $users,
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws Throwable
     */
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
            $id = UserId::generate();
            $this->commands->dispatch(new RegisterUser($id, $email, new Secret($password), $admin ? ['ROLE_ADMIN'] : []));
            $user = $this->users->find($id) ?? throw new LogicException('The new user was not saved.');
        } catch (UserAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (ValidationFailedException $e) {
            PasswordPrompt::printViolations($io, $e);

            return Command::INVALID;
        }

        $io->success(sprintf('Created user %s (%s) [%s].', $user->email, $user->id, implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
