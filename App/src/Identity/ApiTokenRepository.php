<?php

declare(strict_types=1);

namespace App\Identity;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    public function findOneByAccessTokenHash(string $hash): ?ApiToken
    {
        return $this->findOneBy(['accessTokenHash' => $hash]);
    }

    public function findOneByRefreshTokenHash(string $hash): ?ApiToken
    {
        return $this->findOneBy(['refreshTokenHash' => $hash]);
    }

    /**
     * @return int number of sessions revoked
     */
    public function deleteAllForUser(User $user): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * @return int number of sessions removed
     */
    public function deleteExpired(DateTimeImmutable $now): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.refreshExpiresAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
