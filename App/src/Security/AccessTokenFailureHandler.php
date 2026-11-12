<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * A Bearer token was sent but is unknown, expired or revoked (RFC 6750 §3.1).
 * Clients should try /api/auth/refresh, then fall back to logging in again.
 */
final class AccessTokenFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['error' => 'Invalid or expired access token.'],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer error="invalid_token"'],
        );
    }
}
