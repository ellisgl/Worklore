<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\User;
use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * GET /api/me response: the signed-in user's account.
 */
final readonly class AccountSummary
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'email')]
        public string $email,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public static function of(User $user): self
    {
        return new self($user->id->toRfc4122(), $user->email, $user->createdAt);
    }
}
