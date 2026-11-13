<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryEntity>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryEntity::class);
    }

    /**
     * The user's categories, sorted by name.
     *
     * By default, only active categories are returned: Pass $includeArchived = true for all.
     *
     * @return list<CategoryEntity>
     */
    public function getAllCategoriesByUser(User $user, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('c')
            ->where('c.user = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->orderBy('c.name', 'ASC');
        if (!$includeArchived) {
            $qb->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }
}
