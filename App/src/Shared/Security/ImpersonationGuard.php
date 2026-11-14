<?php

declare(strict_types=1);

namespace App\Shared\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;

/**
 * Enforces #[NotWhileImpersonating] and #[OnlyWhileImpersonating].
 */
#[AsEventListener]
final readonly class ImpersonationGuard
{
    public function __construct(private Impersonation $impersonation)
    {
    }

    public function __invoke(ControllerArgumentsEvent $event): void
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
