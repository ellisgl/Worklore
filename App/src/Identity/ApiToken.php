<?php

declare(strict_types=1);

namespace App\Identity;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * One login session: an access/refresh token pair.
 *
 * Only SHA-256 hashes of the tokens are stored, so a database leak doesn't
 * leak usable credentials. Refreshing rotates both hashes in place.
 *
 * Doctrine mapping: src/Identity/Config/ApiToken.orm.xml.
 */
final class ApiToken
{
    private(set) Uuid $id;
    private(set) string $accessTokenHash;
    private(set) DateTimeImmutable $accessExpiresAt;
    private(set) string $refreshTokenHash;
    private(set) DateTimeImmutable $refreshExpiresAt;

    /**
     * $createdAt comes from the caller's clock (TokenManager), so tests that
     * mock the clock see consistent times.
     */
    public function __construct(
        private(set) readonly User $user,
        private(set) DateTimeImmutable $createdAt,
        string $accessTokenHash,
        DateTimeImmutable $accessExpiresAt,
        string $refreshTokenHash,
        DateTimeImmutable $refreshExpiresAt,
    ) {
        $this->id = Uuid::v7();
        $this->rotate($accessTokenHash, $accessExpiresAt, $refreshTokenHash, $refreshExpiresAt);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function rotate(
        string $accessTokenHash,
        DateTimeImmutable $accessExpiresAt,
        string $refreshTokenHash,
        DateTimeImmutable $refreshExpiresAt,
    ): self {
        $this->accessTokenHash = $accessTokenHash;
        $this->accessExpiresAt = $accessExpiresAt;
        $this->refreshTokenHash = $refreshTokenHash;
        $this->refreshExpiresAt = $refreshExpiresAt;

        return $this;
    }

    public function isAccessTokenValid(DateTimeImmutable $now): bool
    {
        return $this->accessExpiresAt > $now;
    }

    public function isRefreshTokenValid(DateTimeImmutable $now): bool
    {
        return $this->refreshExpiresAt > $now;
    }
}
