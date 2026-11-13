<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\UserManager;
use OpenApi\Attributes as OA;
use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/me/password body. The new password follows the same rules as
 * UserManager (and the console commands).
 */
#[OA\Schema(required: ['current_password', 'new_password'])]
final readonly class ChangePasswordRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[OA\Property(format: 'password')]
        #[SensitiveParameter]
        public string $currentPassword = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: UserManager::MIN_PASSWORD_LENGTH, max: 4096)]
        #[OA\Property(format: 'password')]
        #[SensitiveParameter]
        public string $newPassword = '',
    ) {
    }
}
