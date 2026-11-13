<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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
     * The category with this id, if it belongs to the user; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too (e.g. to unarchive them).
     */
    public function findOneByIdForUser(Uuid $id, User $user): ?CategoryEntity
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
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
