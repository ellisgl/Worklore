<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Shared\Name;
use App\Shared\Owner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for the Taxonomy aggregate: categories and their tags. Save a
 * category and its tags are saved with it; there's no separate tag
 * repository.
 *
 * Every lookup is scoped to a user, so endpoints answer 404 instead of
 * revealing someone else's records. By-id lookups return archived records too
 * (e.g. to unarchive them); list methods leave out archived records, and tags
 * of an archived category, unless $includeArchived.
 *
 * @extends ServiceEntityRepository<CategoryEntity>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryEntity::class);
    }

    // ---- categories -------------------------------------------------------

    public function findOneByIdForUser(CategoryId $id, Owner $user): ?CategoryEntity
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * The user's categories, sorted by name.
     *
     * @return list<CategoryEntity>
     */
    public function getAllCategoriesByUser(Owner $user, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    // ---- tags -------------------------------------------------------------

    public function findTagByIdForUser(TagId $id, Owner $user): ?TagEntity
    {
        return $this->getEntityManager()->getRepository(TagEntity::class)->findOneBy(['id' => $id, 'user' => $user]);
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
        $qb = $this->select(TagEntity::class, 't')
            ->select('1')
            ->where('t.category = :category')
            ->andWhere('t.nameKey = :nameKey')
            ->setParameter('category', $category)
            ->setParameter('nameKey', Name::keyOf($name))
            ->setMaxResults(1);

        if (null !== $except) {
            $qb->andWhere('t <> :except')
                ->setParameter('except', $except);
        }

        return null !== $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * The user's tags, sorted by category name and then tag name. The category
     * is fetched in the same query, so reading $tag->category doesn't trigger
     * a query per tag.
     *
     * @return list<TagEntity>
     */
    public function getAllTagsByUser(Owner $user, bool $includeArchived = false): array
    {
        $qb = $this->select(TagEntity::class, 't')
            ->innerJoin('t.category', 'c')
            ->addSelect('c')
            ->where('t.user = :user')
            ->setParameter('user', $user)
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
     * @return list<TagEntity>
     */
    public function getAllTagsByCategory(Owner $user, CategoryEntity $category, bool $includeArchived = false): array
    {
        $qb = $this->select(TagEntity::class, 't')
            ->innerJoin('t.category', 'c')
            ->where('t.user = :user')
            ->andWhere('t.category = :category')
            ->setParameter('user', $user)
            ->setParameter('category', $category)
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('t.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('t.archivedAt IS NULL')
                ->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * A query over the aggregate's tags.
     *
     * @param class-string $class
     */
    private function select(string $class, string $alias): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()->select($alias)->from($class, $alias);
    }
}
