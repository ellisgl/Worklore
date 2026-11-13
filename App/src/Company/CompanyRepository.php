<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\Name;
use App\Shared\Owner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * The user's products at a company, sorted by name.
     *
     * @return list<ProductEntity>
     */
    public function getAllProductsByCompany(Owner $owner, CompanyEntity $company, bool $includeArchived = false): array
    {
        $qb = $this->products()
            ->innerJoin('p.company', 'c')
            ->where('p.owner = :owner')
            ->andWhere('p.company = :company')
            ->setParameter('owner', $owner)
            ->setParameter('company', $company)
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('p.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('p.archivedAt IS NULL')
                ->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    // ---- projects ---------------------------------------------------------

    public function findProjectByIdForOwner(ProjectId $id, Owner $owner): ?ProjectEntity
    {
        return $this->getEntityManager()->getRepository(ProjectEntity::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /**
     * The user's projects for a product, sorted by name.
     *
     * @return list<ProjectEntity>
     */
    public function getAllProjectsByProduct(Owner $owner, ProductEntity $product, bool $includeArchived = false): array
    {
        $qb = $this->projects()
            ->innerJoin('pj.product', 'p')
            ->innerJoin('p.company', 'c')
            ->where('pj.owner = :owner')
            ->andWhere('pj.product = :product')
            ->setParameter('owner', $owner)
            ->setParameter('product', $product)
            ->orderBy('pj.name', 'ASC')
            ->addOrderBy('pj.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('pj.archivedAt IS NULL')
                ->andWhere('p.archivedAt IS NULL')
                ->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    // ---- positions --------------------------------------------------------

    public function findPositionByIdForOwner(PositionId $id, Owner $owner): ?PositionEntity
    {
        return $this->getEntityManager()->getRepository(PositionEntity::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /**
     * The user's positions at a company, newest start date first.
     *
     * @return list<PositionEntity>
     */
    public function getAllPositionsByCompany(Owner $owner, CompanyEntity $company, bool $includeArchived = false): array
    {
        $qb = $this->positions()
            ->innerJoin('p.company', 'c')
            ->where('p.owner = :owner')
            ->andWhere('p.company = :company')
            ->setParameter('owner', $owner)
            ->setParameter('company', $company)
            ->orderBy('p.period.start', 'DESC')
            ->addOrderBy('p.id', 'DESC');

        if (!$includeArchived) {
            $qb->andWhere('p.archivedAt IS NULL')
                ->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * The user's current positions (no end date) across all companies, newest
     * start date first. Always leaves out archived positions and positions at
     * archived companies. The company is fetched in the same query.
     *
     * @return list<PositionEntity>
     */
    public function getCurrentPositionsByOwner(Owner $owner): array
    {
        return $this->positions()
            ->innerJoin('p.company', 'c')
            ->addSelect('c')
            ->where('p.owner = :owner')
            ->andWhere('p.period.end IS NULL')
            ->andWhere('p.archivedAt IS NULL')
            ->andWhere('c.archivedAt IS NULL')
            ->setParameter('owner', $owner)
            ->orderBy('p.period.start', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** A query over the aggregate's products, aliased "p". */
    private function products(): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()->select('p')->from(ProductEntity::class, 'p');
    }

    /** A query over the aggregate's projects, aliased "pj". */
    private function projects(): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()->select('pj')->from(ProjectEntity::class, 'pj');
    }

    /** A query over the aggregate's positions, aliased "p". */
    private function positions(): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()->select('p')->from(PositionEntity::class, 'p');
    }
}
