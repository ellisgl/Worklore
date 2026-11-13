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
 * Every lookup is scoped to a user, so endpoints answer 404 instead of
 * revealing someone else's records. By-id lookups return archived records too
 * (e.g. to unarchive them); list methods leave out archived records, and
 * records whose parent or ancestor is archived, unless $includeArchived.
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

    public function findOneByIdForUser(CompanyId $id, Owner $user): ?CompanyEntity
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * The user's companies, sorted by name.
     *
     * @return list<CompanyEntity>
     */
    public function getAllCompaniesByUser(Owner $user, bool $includeArchived = false): array
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

    /**
     * Whether the user already has a company with this name, ignoring case
     * (archived ones included, matching uniq_company_user_name). Pass the
     * company being renamed as $except so keeping its current name isn't a
     * clash.
     *
     * Check this before saving to answer 409/422; the unique constraint stays
     * as the backstop for two requests racing each other.
     */
    public function nameExistsForUser(Owner $user, string $name, ?CompanyEntity $except = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('1')
            ->where('c.user = :user')
            ->andWhere('c.nameKey = :nameKey')
            ->setParameter('user', $user)
            ->setParameter('nameKey', Name::keyOf($name))
            ->setMaxResults(1);

        if (null !== $except) {
            $qb->andWhere('c <> :except')
                ->setParameter('except', $except);
        }

        return null !== $qb->getQuery()->getOneOrNullResult();
    }

    // ---- products ---------------------------------------------------------

    public function findProductByIdForUser(ProductId $id, Owner $user): ?ProductEntity
    {
        return $this->getEntityManager()->getRepository(ProductEntity::class)->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * The user's products at a company, sorted by name.
     *
     * @return list<ProductEntity>
     */
    public function getAllProductsByCompany(Owner $user, CompanyEntity $company, bool $includeArchived = false): array
    {
        $qb = $this->select(ProductEntity::class, 'p')
            ->innerJoin('p.company', 'c')
            ->where('p.user = :user')
            ->andWhere('p.company = :company')
            ->setParameter('user', $user)
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

    public function findProjectByIdForUser(ProjectId $id, Owner $user): ?ProjectEntity
    {
        return $this->getEntityManager()->getRepository(ProjectEntity::class)->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * The user's projects for a product, sorted by name.
     *
     * @return list<ProjectEntity>
     */
    public function getAllProjectsByProduct(Owner $user, ProductEntity $product, bool $includeArchived = false): array
    {
        $qb = $this->select(ProjectEntity::class, 'pj')
            ->innerJoin('pj.product', 'p')
            ->innerJoin('p.company', 'c')
            ->where('pj.user = :user')
            ->andWhere('pj.product = :product')
            ->setParameter('user', $user)
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

    public function findPositionByIdForUser(PositionId $id, Owner $user): ?PositionEntity
    {
        return $this->getEntityManager()->getRepository(PositionEntity::class)->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * The user's positions at a company, newest start date first.
     *
     * @return list<PositionEntity>
     */
    public function getAllPositionsByCompany(Owner $user, CompanyEntity $company, bool $includeArchived = false): array
    {
        $qb = $this->select(PositionEntity::class, 'p')
            ->innerJoin('p.company', 'c')
            ->where('p.user = :user')
            ->andWhere('p.company = :company')
            ->setParameter('user', $user)
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
    public function getCurrentPositionsByUser(Owner $user): array
    {
        return $this->select(PositionEntity::class, 'p')
            ->innerJoin('p.company', 'c')
            ->addSelect('c')
            ->where('p.user = :user')
            ->andWhere('p.period.end IS NULL')
            ->andWhere('p.archivedAt IS NULL')
            ->andWhere('c.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('p.period.start', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * A query over one of the aggregate's child entities.
     *
     * @param class-string $class
     */
    private function select(string $class, string $alias): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()->select($alias)->from($class, $alias);
    }
}
