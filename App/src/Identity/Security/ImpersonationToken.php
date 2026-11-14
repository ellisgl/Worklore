<?php

declare(strict_types=1);

namespace App\Identity\Security;

use OpenApi\Attributes as OA;

/**
 * An access token that acts as another user, for an admin. There is no
 * refresh token: impersonate again when it expires. POST /api/auth/logout
 * with it ends the impersonation early.
 */
final readonly class ImpersonationToken
{
    #[OA\Property(example: 'Bearer')]
    public string $tokenType;

    public function __construct(
        public string $accessToken,
        #[OA\Property(description: 'Seconds until the token expires', example: 900)]
        public int $expiresIn,
        #[OA\Property(description: 'The user it acts as', format: 'uuid')]
        public string $userId,
        #[OA\Property(format: 'email')]
        public string $userEmail,
    ) {
        $this->tokenType = 'Bearer';
    }
}
