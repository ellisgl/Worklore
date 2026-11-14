<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\UserAlreadyExistsException;
use App\Identity\UserManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Account rules (a valid email, a long enough password) live in UserManager.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserManager $accounts,
    ) {
    }

    /**
     * @throws UserAlreadyExistsException
     * @throws ValidationFailedException  if the email or password breaks the rules
     */
    public function __invoke(RegisterUser $command): void
    {
        $this->accounts->create($command->email, $command->password->reveal(), $command->roles, $command->userId);
    }
}
