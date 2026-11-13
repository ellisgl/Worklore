<?php

declare(strict_types=1);

namespace App\Company;

use App\Identity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductEntity>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductEntity::class);
    }

    /**
     * The product with this id, if it belongs to the user; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too (e.g. to unarchive them).
     */
    public function findOneByIdForUser(ProductId $id, User $user): ?ProductEntity
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * The user's products at a company, sorted by name.
     *
     * By default only active products are returned: archived products, and
     * every product of an archived company, are left out. Pass
     * $includeArchived = true for all.
     *
     * @return list<ProductEntity>
     */
    public function getAllProductsByCompany(User $user, CompanyEntity $company, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('p')
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
}
