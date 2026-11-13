<?php

declare(strict_types=1);

namespace App\Company;

use App\Identity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProjectEntity>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectEntity::class);
    }

    /**
     * The project with this id, if it belongs to the user; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too (e.g. to unarchive them).
     */
    public function findOneByIdForUser(Uuid $id, User $user): ?ProjectEntity
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * The user's projects for a product, sorted by name.
     *
     * By default only active projects are returned: archived projects, and
     * every project under an archived product or company, are left out. Pass
     * $includeArchived = true for all.
     *
     * @return list<ProjectEntity>
     */
    public function getAllProjectsByProduct(User $user, ProductEntity $product, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('pj')
            ->innerJoin('pj.product', 'p')
            ->innerJoin('p.company', 'c')
            ->where('pj.user = :user')
            ->andWhere('pj.product = :product')
            ->setParameter('user', $user->getId(), 'uuid')
            ->setParameter('product', $product->getId(), 'uuid')
            ->orderBy('pj.name', 'ASC')
            ->addOrderBy('pj.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('pj.archivedAt IS NULL')
                ->andWhere('p.archivedAt IS NULL')
                ->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }
}
