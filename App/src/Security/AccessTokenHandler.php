<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Resolves the user behind an "Authorization: Bearer <token>" header.
 */
final readonly class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private TokenManager $tokenManager)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->tokenManager->findValidByAccessToken($accessToken);
        if (null === $token) {
            throw new BadCredentialsException('Invalid or expired access token.');
        }

        $user = $token->getUser();

        return new UserBadge($user->getUserIdentifier(), static fn () => $user);
    }
}
