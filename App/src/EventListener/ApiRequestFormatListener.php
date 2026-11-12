<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Makes every /api response (including 404s and other errors raised before a
 * route matches) render as JSON problem details instead of an HTML error page.
 *
 * Runs before the router (priority 32) so it applies to unmatched paths too.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
final class ApiRequestFormatListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/api/') || '/api' === $request->getPathInfo()) {
            $request->setRequestFormat('json');
        }
    }
}
