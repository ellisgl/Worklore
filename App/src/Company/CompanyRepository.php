<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\Name;
use App\Shared\Owner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for the Company aggregate: companies and everything inside them
 * (products, projects, positions). Save a company and its children are saved
 * with it; there's no separate repository per child.
 *
 * Every lookup is scoped to an owner, so endpoints answer 404 instead of
 * revealing someone else's records. By-id lookups return archived records too
 * (e.g. to unarchive them); list methods leave out archived records, and
 * records whose parent or ancestor is archived, unless $includeArchived.
 *
 * This is the write side's way in. Lists for the API live in
 * Query\CompanyQueries, which returns read models rather than entities; the
 * remaining list methods here move there as their endpoints are built.
 *
 * @extends ServiceEntityRepository<CompanyEntity>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyEntity::class);
    }

    // ---- companies --------------------------------------------------------

    /**
     * Adds a new company (and anything already added inside it). It's saved
     * when the unit of work is flushed, which the command bus does when the
     * handler returns.
     */
    public function add(CompanyEntity $company): void
    {
        $this->getEntityManager()->persist($company);
    }

    public function findOneByIdForOwner(CompanyId $id, Owner $owner): ?CompanyEntity
    {
        return $this->findOneBy(['id' => $id, 'owner' => $owner]);
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
    public function nameExistsForOwner(Owner $owner, string $name, ?CompanyEntity $except = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('1')
            ->where('c.owner = :owner')
            ->andWhere('c.nameKey = :nameKey')
            ->setParameter('owner', $owner)
            ->setParameter('nameKey', Name::keyOf($name))
            ->setMaxResults(1);

        if (null !== $except) {
            $qb->andWhere('c <> :except')
                ->setParameter('except', $except);
        }

        return null !== $qb->getQuery()->getOneOrNullResult();
    }

    // ---- products ---------------------------------------------------------

    public function findProductByIdForOwner(ProductId $id, Owner $owner): ?ProductEntity
    {
        return $this->getEntityManager()->getRepository(ProductEntity::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /**
     * Whether the company already has a product with this name, ignoring case
     * (archived ones included, matching uniq_product_company_name). Pass the product
     * being renamed as $except so keeping its current name isn't a clash.
     *
     * Check this before saving to answer 409; the unique constraint stays as
     * the backstop for two requests racing each other.
     */
    public function productNameExistsInCompany(CompanyEntity $company, string $name, ?ProductEntity $except = null): bool
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(ProductEntity::class, 'p')
            ->where('p.company = :parent')
            ->andWhere('p.nameKey = :nameKey')
            ->setParameter('parent', $company)
            ->setParameter('nameKey', Name::keyOf($name))
            ->setMaxResults(1);

        if (null !== $except) {
            $qb->andWhere('p <> :except')
                ->setParameter('except', $except);
        }

        return null !== $qb->getQuery()->getOneOrNullResult();
    }

    // ---- projects ---------------------------------------------------------

    public function findProjectByIdForOwner(ProjectId $id, Owner $owner): ?ProjectEntity
    {
        return $this->getEntityManager()->getRepository(ProjectEntity::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    // ---- positions --------------------------------------------------------

    public function findPositionByIdForOwner(PositionId $id, Owner $owner): ?PositionEntity
    {
        return $this->getEntityManager()->getRepository(PositionEntity::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }
}
