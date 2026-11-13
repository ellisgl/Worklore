<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<PositionEntity>
 */
class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PositionEntity::class);
    }

    /**
     * The position with this id, if it belongs to the user; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too (e.g. to unarchive them).
     */
    public function findOneByIdForUser(Uuid $id, User $user): ?PositionEntity
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * The user's positions at a company, newest start date first.
     *
     * Filtering on the user too means passing someone else's company returns
     * nothing. By default only active positions are returned: pass
     * $includeArchived = true for all.
     *
     * @return list<PositionEntity>
     */
    public function getAllPositionsByCompany(User $user, CompanyEntity $company, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb->select('p')
            ->where('p.user = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->andWhere('p.company = :company')
            ->setParameter('company', $company->getId(), 'uuid')
            ->orderBy('p.startDate', 'DESC');
        if (!$includeArchived) {
            $qb->andWhere('p.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }
}
