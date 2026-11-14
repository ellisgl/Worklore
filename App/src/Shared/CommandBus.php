<?php

declare(strict_types=1);

namespace App\Shared;

use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;

use function array_values;
use function count;

/**
 * command.bus for callers that want a command to behave like a call: it
 * returns what the handler returned and throws what the handler threw
 * (Messenger wraps that in HandlerFailedException). The console commands
 * use it; controllers can dispatch on command.bus directly.
 */
final readonly class CommandBus
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private MessageBusInterface $commandBus,
    ) {
    }

    /**
     * @throws ExceptionInterface if the bus itself fails
     * @throws Throwable          whatever the handler threw
     */
    public function dispatch(object $command): mixed
    {
        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            $wrapped = $e->getWrappedExceptions();
            throw 1 === count($wrapped) ? array_values($wrapped)[0] : $e;
        }

        return $envelope->last(HandledStamp::class)?->getResult();
    }
}
