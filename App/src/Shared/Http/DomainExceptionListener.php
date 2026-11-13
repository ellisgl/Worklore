<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\ConflictException;
use App\Shared\InvalidValueException;
use App\Shared\OwnershipMismatchException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Turns domain exceptions from /api requests into JSON error responses, in
 * the same {"error": "..."} shape as the auth endpoints, so controllers and
 * command handlers can just throw:
 *
 * - InvalidValueException      → 422, with its message
 * - ConflictException          → 409, with its message
 * - OwnershipMismatchException → 404 "Not found.", so it doesn't reveal that
 *                                another user's record exists
 *
 * Anything else is left alone (a 500, or Symfony's own handling).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class DomainExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/') && '/api' !== $path) {
            return;
        }

        $response = match (true) {
            ($e = self::unwrap($event->getThrowable())) instanceof InvalidValueException => self::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY),
            $e instanceof ConflictException => self::error($e->getMessage(), Response::HTTP_CONFLICT),
            $e instanceof OwnershipMismatchException => self::error('Not found.', Response::HTTP_NOT_FOUND),
            default => null,
        };

        if (null !== $response) {
            $event->setResponse($response);
        }
    }

    /**
     * Messenger wraps whatever a handler throws; a command has one handler,
     * so the domain exception is the single wrapped one.
     */
    private static function unwrap(Throwable $e): Throwable
    {
        while ($e instanceof HandlerFailedException && 1 === \count($e->getWrappedExceptions())) {
            $e = current($e->getWrappedExceptions());
        }

        return $e;
    }

    private static function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
