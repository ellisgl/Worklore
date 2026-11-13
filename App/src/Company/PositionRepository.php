<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
