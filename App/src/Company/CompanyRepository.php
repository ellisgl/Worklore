<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<CompanyEntity>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyEntity::class);
    }

    /**
     * The company with this id, if it belongs to the user; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too (e.g. to unarchive them).
     */
    public function findOneByIdForUser(Uuid $id, User $user): ?CompanyEntity
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * The user's companies, sorted by name.
     *
     * By default only active companies are returned: pass $includeArchived =
     * true for all.
     *
     * @return list<CompanyEntity>
     */
    public function getAllCompaniesByUser(User $user, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Whether the user already has a company with this name, ignoring case
     * (archived ones included, matching uniq_company_user_name). Pass the
     * company being renamed as $except so keeping its current name isn't a
     * clash.
     *
     * Check this before saving to answer 409/422; the unique constraint stays
     * as the backstop for two requests racing each other.
     */
    public function nameExistsForUser(User $user, string $name, ?CompanyEntity $except = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('1')
            ->where('c.user = :user')
            ->andWhere('c.nameKey = :nameKey')
            ->setParameter('user', $user->getId(), 'uuid')
            ->setParameter('nameKey', CompanyEntity::nameKey($name))
            ->setMaxResults(1);

        if (null !== $except) {
            $qb->andWhere('c.id <> :except')
                ->setParameter('except', $except->getId(), 'uuid');
        }

        return null !== $qb->getQuery()->getOneOrNullResult();
    }
}
