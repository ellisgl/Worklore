<?php

declare(strict_types=1);

namespace App\Identity\Security;

use OpenApi\Attributes as OA;

/**
 * Freshly issued credentials, returned to the client once and never stored in plain text.
 */
final readonly class TokenPair
{
    #[OA\Property(example: 'Bearer')]
    public string $tokenType;

    public function __construct(
        public string $accessToken,
        #[OA\Property(description: 'Seconds until the access token expires')]
        public int $expiresIn,
        public string $refreshToken,
        #[OA\Property(description: 'Seconds until the refresh token expires')]
        public int $refreshExpiresIn,
    ) {
        $this->tokenType = 'Bearer';
    }
}
