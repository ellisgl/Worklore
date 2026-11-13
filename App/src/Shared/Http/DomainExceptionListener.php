<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\ConflictException;
use App\Shared\InvalidValueException;
use App\Shared\NotFoundException;
use App\Shared\OwnershipMismatchException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function count;
use function preg_match;

/**
 * Turns domain exceptions from /api requests into JSON error responses, in
 * the same {"error": "..."} shape as the auth endpoints, so controllers and
 * command handlers can just throw:
 *
 * - InvalidValueException      → 422, with its message
 * - ConflictException          → 409, with its message
 * - NotFoundException          → 404, with its message
 * - OwnershipMismatchException → 404 "Not found.", so it doesn't reveal that
 *                                another user's record exists
 * - a name's unique constraint  → 409, like the handlers' own *NameTaken
 *   (UniqueConstraintViolation)  check; it fires when two requests race
 *                                past that check with the same name
 *
 * Anything else is left alone (a 500, or Symfony's own handling).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class DomainExceptionListener
{
    /**
     * The unique constraints a request can race on (see the modules'
     * *.orm.xml): names, and a sign-up's email. Others, such as token hashes,
     * aren't reachable that way, so they stay a 500.
     */
    private const array NAME_CONSTRAINTS = [
        'uniq_app_user_email' => 'An account with this email already exists.',
        'uniq_company_user_name' => 'You already have a company with this name.',
        'uniq_product_company_name' => 'This company already has a product with this name.',
        'uniq_project_product_name' => 'This product already has a project with this name.',
        'uniq_category_user_name' => 'You already have a category with this name.',
        'uniq_tag_category_name' => 'This category already has a tag with this name.',
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/') && '/api' !== $path) {
            return;
        }

        $response = match (true) {
            ($e = self::unwrap($event->getThrowable())) instanceof InvalidValueException => self::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY),
            $e instanceof ConflictException => self::error($e->getMessage(), Response::HTTP_CONFLICT),
            $e instanceof NotFoundException => self::error($e->getMessage(), Response::HTTP_NOT_FOUND),
            $e instanceof OwnershipMismatchException => self::error('Not found.', Response::HTTP_NOT_FOUND),
            $e instanceof UniqueConstraintViolationException => self::nameTaken($e),
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
        while ($e instanceof HandlerFailedException && 1 === count($e->getWrappedExceptions())) {
            $e = current($e->getWrappedExceptions());
        }

        return $e;
    }

    /**
     * A 409 if the violated constraint is one of NAME_CONSTRAINTS. PostgreSQL
     * names it in the message: ... violates unique constraint "uniq_...".
     */
    private static function nameTaken(UniqueConstraintViolationException $e): ?JsonResponse
    {
        if (1 !== preg_match('/unique constraint "([^"]+)"/', $e->getMessage(), $match)) {
            return null;
        }
        $message = self::NAME_CONSTRAINTS[$match[1]] ?? null;

        return null === $message ? null : self::error($message, Response::HTTP_CONFLICT);
    }

    private static function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
