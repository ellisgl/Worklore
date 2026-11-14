<?php

declare(strict_types=1);

namespace App\Identity\Query;

use DateTimeImmutable;
use OpenApi\Attributes as OA;

/**
 * A user as admins see them.
 */
final readonly class UserSummary
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'email')]
        public string $email,
        public bool $admin,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt,
    ) {
    }
}
