<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Issues, validates, rotates and revokes opaque API tokens.
 */
final readonly class TokenManager
{
    private const string ACCESS_PREFIX = 'wla_';
    private const string REFRESH_PREFIX = 'wlr_';

    public function __construct(
        private ApiTokenRepository $tokens,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        #[Autowire('%app.auth.access_token_ttl%')]
        private int $accessTtl,
        #[Autowire('%app.auth.refresh_token_ttl%')]
        private int $refreshTtl,
    ) {
    }

    public function issue(User $user): TokenPair
    {
        [$pair, $hashes] = $this->generate();
        $now = $this->clock->now();

        $this->em->persist(new ApiToken(
            $user,
            $hashes['access'],
            $now->modify("+{$this->accessTtl} seconds"),
            $hashes['refresh'],
            $now->modify("+{$this->refreshTtl} seconds"),
        ));
        $this->em->flush();

        return $pair;
    }

    public function findValidByAccessToken(string $accessToken): ?ApiToken
    {
        $token = $this->tokens->findOneByAccessTokenHash(self::hash($accessToken));

        return $token?->isAccessTokenValid($this->clock->now()) ? $token : null;
    }

    /**
     * Exchanges a refresh token for a new pair. The old pair stops working.
     *
     * Returns null if the refresh token is unknown, expired, was already used,
     * or belongs to an archived user.
     */
    public function refresh(string $refreshToken): ?TokenPair
    {
        $oldHash = self::hash($refreshToken);
        $now = $this->clock->now();

        $token = $this->tokens->findOneByRefreshTokenHash($oldHash);
        if (null === $token || !$token->isRefreshTokenValid($now) || $token->getUser()->isArchived()) {
            return null;
        }

        [$pair, $hashes] = $this->generate();

        // Conditional update on the old hash: if two requests race with the same
        // refresh token, only one matches and the other is rejected.
        $updated = $this->em->createQueryBuilder()
            ->update(ApiToken::class, 't')
            ->set('t.accessTokenHash', ':access')
            ->set('t.accessExpiresAt', ':accessExpires')
            ->set('t.refreshTokenHash', ':refresh')
            ->set('t.refreshExpiresAt', ':refreshExpires')
            ->where('t.id = :id')
            ->andWhere('t.refreshTokenHash = :oldHash')
            ->setParameter('access', $hashes['access'])
            ->setParameter('accessExpires', $now->modify("+{$this->accessTtl} seconds"), 'datetime_immutable')
            ->setParameter('refresh', $hashes['refresh'])
            ->setParameter('refreshExpires', $now->modify("+{$this->refreshTtl} seconds"), 'datetime_immutable')
            ->setParameter('id', $token->getId(), 'uuid')
            ->setParameter('oldHash', $oldHash)
            ->getQuery()
            ->execute();

        return 1 === $updated ? $pair : null;
    }

    public function revokeByAccessToken(string $accessToken): void
    {
        $this->em->createQueryBuilder()
            ->delete(ApiToken::class, 't')
            ->where('t.accessTokenHash = :hash')
            ->setParameter('hash', self::hash($accessToken))
            ->getQuery()
            ->execute();
    }

    public function revokeAllForUser(User $user): int
    {
        return $this->tokens->deleteAllForUser($user);
    }

    public function purgeExpired(): int
    {
        return $this->tokens->deleteExpired($this->clock->now());
    }

    /**
     * @return array{TokenPair, array{access: string, refresh: string}}
     */
    private function generate(): array
    {
        $access = self::ACCESS_PREFIX.self::randomToken();
        $refresh = self::REFRESH_PREFIX.self::randomToken();

        return [
            new TokenPair($access, $this->accessTtl, $refresh, $this->refreshTtl),
            ['access' => self::hash($access), 'refresh' => self::hash($refresh)],
        ];
    }

    private static function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
