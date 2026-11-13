<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\UserManager;
use OpenApi\Attributes as OA;
use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/auth/signup body; the same rules as UserManager::create().
 */
#[OA\Schema(required: ['email', 'password'])]
final readonly class SignUpRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email(normalizer: 'trim')]
        #[Assert\Length(max: 180, normalizer: 'trim')]
        #[OA\Property(format: 'email')]
        public string $email = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: UserManager::MIN_PASSWORD_LENGTH, max: 4096)]
        #[OA\Property(format: 'password')]
        #[SensitiveParameter]
        public string $password = '',
    ) {
    }
}
