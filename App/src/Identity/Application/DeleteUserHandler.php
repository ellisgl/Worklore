<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\UserManager;
use App\Identity\UserRepository;
use App\Shared\UserDeleted;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Their sessions, events and projections go with them (ON DELETE CASCADE).
 * UserDeleted goes out on event.bus after the commit, for what lives
 * outside the database (their media files).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteUserHandler
{
    public function __construct(
        private UserRepository $users,
        private UserManager $accounts,
        // Named autowiring: $eventBus is the event.bus service.
        private MessageBusInterface $eventBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws UserNotFound
     */
    public function __invoke(DeleteUser $command): void
    {
        $user = $this->users->find($command->userId)
            ?? throw new UserNotFound();

        $this->accounts->delete($user);
        $this->eventBus->dispatch(new UserDeleted($command->userId), [new DispatchAfterCurrentBusStamp()]);
    }
}
