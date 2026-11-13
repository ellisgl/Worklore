<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TagEntity>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TagEntity::class);
    }

    /**
     * The user's tags, sorted by category name and then tag name.
     *
     * By default, only active tags are returned: archived tags, and tags whose
     * category is archived, are left out. Pass $includeArchived = true for all.
     *
     * The category is fetched in the same query, so reading $tag->category
     * doesn't trigger a query per tag.
     *
     * @return list<TagEntity>
     */
    public function getAllTagsByUser(User $user, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('t');
        $qb->innerJoin('t.category', 'c')
           ->addSelect('c')
           ->where('t.user = :user')
           ->setParameter('user', $user->getId(), 'uuid')
           ->orderBy('c.name', 'ASC')
           ->addOrderBy('t.name', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('t.archivedAt IS NULL')
               ->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }
}
