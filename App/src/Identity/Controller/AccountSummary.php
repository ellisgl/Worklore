<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\User;
use App\Shared\UserId;
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
        #[OA\Property(description: 'The admin acting as this user, when the token is an impersonation', format: 'uuid')]
        public ?string $impersonatedBy = null,
    ) {
    }

    public static function of(User $user, ?UserId $impersonatorId = null): self
    {
        return new self($user->id->toRfc4122(), $user->email, $user->createdAt, $impersonatorId?->toRfc4122());
    }
}
