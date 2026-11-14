<?php

declare(strict_types=1);

namespace App\Shared\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Enforces #[NotWhileImpersonating] and #[OnlyWhileImpersonating]. Runs
 * when the controller is chosen, before its arguments are resolved, so a
 * refused request is a 403 even if its body is invalid too.
 */
#[AsEventListener]
final readonly class ImpersonationGuard
{
    public function __construct(private Impersonation $impersonation)
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $attributes = $event->getAttributes();
        $impersonating = $this->impersonation->isActive();

        $refusal = match (true) {
            $impersonating && [] !== ($attributes[NotWhileImpersonating::class] ?? []) => 'Not allowed while impersonating a user.',
            !$impersonating && [] !== ($attributes[OnlyWhileImpersonating::class] ?? []) => 'Only an admin impersonating this user may do this.',
            default => null,
        };

        if (null !== $refusal) {
            $event->setController(static fn (): JsonResponse => new JsonResponse(['error' => $refusal], Response::HTTP_FORBIDDEN));
        }
    }
}
