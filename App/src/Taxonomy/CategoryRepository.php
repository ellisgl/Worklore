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
 * (e.g. to unarchive them). Lists are read through the query classes
 * (App\Taxonomy\Query).
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

    /**
     * Saves a new category, and the tags inside it, on the next flush.
     */
    public function add(CategoryEntity $category): void
    {
        $this->getEntityManager()->persist($category);
    }

    public function findOneByIdForOwner(CategoryId $id, Owner $owner): ?CategoryEntity
    {
        return $this->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /**
     * Whether the owner already has a category with this name, ignoring case
     * (archived ones included, matching uniq_category_user_name). Pass the category
     * being renamed as $except so keeping its current name isn't a clash.
     *
     * Check this before saving to answer 409; the unique constraint stays as
     * the backstop for two requests racing each other.
     */
    public function nameExistsForOwner(Owner $owner, string $name, ?CategoryEntity $except = null): bool
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(CategoryEntity::class, 'c')
            ->where('c.owner = :parent')
            ->andWhere('c.nameKey = :nameKey')
            ->setParameter('parent', $owner)
            ->setParameter('nameKey', Name::keyOf($name))
            ->setMaxResults(1);

        if (null !== $except) {
            $qb->andWhere('c <> :except')
                ->setParameter('except', $except);
        }

        return null !== $qb->getQuery()->getOneOrNullResult();
    }

    // ---- tags -------------------------------------------------------------

    public function findTagByIdForOwner(TagId $id, Owner $owner): ?TagEntity
    {
        return $this->getEntityManager()->getRepository(TagEntity::class)->findOneBy(['id' => $id, 'owner' => $owner]);
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
        $qb = $this->tags()
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
     * A query over the aggregate's tags, aliased "t".
     */
    private function tags(): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()->select('t')->from(TagEntity::class, 't');
    }
}
