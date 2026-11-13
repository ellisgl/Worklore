<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['refresh_token'])]
final readonly class RefreshTokenRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $refreshToken = '',
    ) {
    }
}
