<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\Uid\Uuid;

/**
 * One login session: an access/refresh token pair.
 *
 * Only SHA-256 hashes of the tokens are stored, so a database leak doesn't
 * leak usable credentials. Refreshing rotates both hashes in place.
 *
 * Doctrine mapping: config/doctrine/Entity/ApiToken.orm.xml.
 */
class ApiToken
{
    private Uuid $id;

    private string $accessTokenHash;

    private \DateTimeImmutable $accessExpiresAt;

    private string $refreshTokenHash;

    private \DateTimeImmutable $refreshExpiresAt;

    private \DateTimeImmutable $createdAt;

    public function __construct(
        private User $user,
        string $accessTokenHash,
        \DateTimeImmutable $accessExpiresAt,
        string $refreshTokenHash,
        \DateTimeImmutable $refreshExpiresAt,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->rotate($accessTokenHash, $accessExpiresAt, $refreshTokenHash, $refreshExpiresAt);
    }

    public function rotate(
        string $accessTokenHash,
        \DateTimeImmutable $accessExpiresAt,
        string $refreshTokenHash,
        \DateTimeImmutable $refreshExpiresAt,
    ): void {
        $this->accessTokenHash = $accessTokenHash;
        $this->accessExpiresAt = $accessExpiresAt;
        $this->refreshTokenHash = $refreshTokenHash;
        $this->refreshExpiresAt = $refreshExpiresAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function isAccessTokenValid(\DateTimeImmutable $now): bool
    {
        return $this->accessExpiresAt > $now;
    }

    public function isRefreshTokenValid(\DateTimeImmutable $now): bool
    {
        return $this->refreshExpiresAt > $now;
    }

    public function getAccessExpiresAt(): \DateTimeImmutable
    {
        return $this->accessExpiresAt;
    }

    public function getRefreshExpiresAt(): \DateTimeImmutable
    {
        return $this->refreshExpiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
