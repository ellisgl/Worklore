<?php

declare(strict_types=1);

namespace App\Identity;

use DateTimeImmutable;

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
    private(set) ApiTokenId $id;
    private(set) string $accessTokenHash;
    private(set) DateTimeImmutable $accessExpiresAt;
    private(set) string $refreshTokenHash;
    private(set) DateTimeImmutable $refreshExpiresAt;

    /**
     * $createdAt comes from the caller's clock (TokenManager), so tests that
     * mock the clock see consistent times. It isn't readonly, like createdAt on
     * every entity: $em->refresh() rebuilds it as a new object, which Doctrine
     * can't write to a readonly property (see HydrationTest).
     *
     * @noinspection PhpPropertyCanBeReadonlyInspection
     */
    public function __construct(
        private(set) readonly User $user,
        private(set) DateTimeImmutable $createdAt,
        string $accessTokenHash,
        DateTimeImmutable $accessExpiresAt,
        string $refreshTokenHash,
        DateTimeImmutable $refreshExpiresAt,
        /** The admin acting as $user, for an impersonation token; null for the user's own */
        private(set) readonly ?User $impersonator = null,
    ) {
        $this->id = ApiTokenId::generate();
        $this->rotate($accessTokenHash, $accessExpiresAt, $refreshTokenHash, $refreshExpiresAt);
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

    /**
     * Impersonation tokens can't be refreshed: they end when they expire.
     */
    public function isRefreshTokenValid(DateTimeImmutable $now): bool
    {
        return null === $this->impersonator && $this->refreshExpiresAt > $now;
    }
}
