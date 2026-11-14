<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing\Listener;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

use function get_debug_type;

/**
 * event.bus middleware: a listener that fails is logged, not rethrown.
 * Listeners run after the command has committed, so the change they react
 * to has already happened; the client must not get an error for it. Every
 * other listener for the event still runs (Messenger calls them all before
 * it reports failures).
 */
#[WithMonologChannel('domain')]
final readonly class IsolateEventListeners implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $handler => $failure) {
                $this->logger->error('Event listener {listener} failed on {event}: {message}', [
                    'listener' => $handler,
                    'event' => get_debug_type($envelope->getMessage()),
                    'message' => $failure->getMessage(),
                    'exception' => $failure,
                ]);
            }

            return $e->getEnvelope();
        }
    }
}
