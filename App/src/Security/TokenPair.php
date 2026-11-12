<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Freshly issued credentials, returned to the client once and never stored in plain text.
 */
final readonly class TokenPair
{
    public string $tokenType;

    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public string $refreshToken,
        public int $refreshExpiresIn,
    ) {
        $this->tokenType = 'Bearer';
    }
}
