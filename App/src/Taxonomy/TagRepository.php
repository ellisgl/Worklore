<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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
     * The tag with this id, if it belongs to the user; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too (e.g. to unarchive them).
     */
    public function findOneByIdForUser(Uuid $id, User $user): ?TagEntity
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * Whether the category already has a tag with this name, ignoring case
     * (archived ones included, matching uniq_tag_category_name). Pass the tag
     * being renamed or moved as $except so keeping its current name isn't a
     * clash.
     *
     * Check this before saving to answer 409/422; the unique constraint stays
     * as the backstop for two requests racing each other.
     */
    public function nameExistsInCategory(CategoryEntity $category, string $name, ?TagEntity $except = null): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('1')
            ->where('t.category = :category')
            ->andWhere('t.nameKey = :nameKey')
            ->setParameter('category', $category->getId(), 'uuid')
            ->setParameter('nameKey', TagEntity::nameKey($name))
            ->setMaxResults(1);

        if (null !== $except) {
            $qb->andWhere('t.id <> :except')
                ->setParameter('except', $except->getId(), 'uuid');
        }

        return null !== $qb->getQuery()->getOneOrNullResult();
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
        $qb = $this->createQueryBuilder('t')
            ->innerJoin('t.category', 'c')
            ->addSelect('c')
            ->where('t.user = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('c.id', 'ASC')     // keeps same-named categories' tags together
            ->addOrderBy('t.name', 'ASC')
            ->addOrderBy('t.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('t.archivedAt IS NULL')
               ->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * The user's tags in one category, sorted by name.
     *
     * By default only active tags are returned: archived tags, and every tag
     * of an archived category, are left out. Pass $includeArchived = true for
     * all.
     *
     * @return list<TagEntity>
     */
    public function getAllTagsByCategory(User $user, CategoryEntity $category, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('t')
            ->innerJoin('t.category', 'c')
            ->where('t.user = :user')
            ->andWhere('t.category = :category')
            ->setParameter('user', $user->getId(), 'uuid')
            ->setParameter('category', $category->getId(), 'uuid')
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('t.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('t.archivedAt IS NULL')
                ->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }
}
