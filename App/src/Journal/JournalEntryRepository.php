<?php

declare(strict_types=1);

namespace App\Journal;

use App\Company\ProjectEntity;
use App\Identity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JournalEntryEntity>
 */
class JournalEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalEntryEntity::class);
    }

    /**
     * The journal entry with this id, if it belongs to the user; null
     * otherwise, so an endpoint answers 404 instead of revealing someone
     * else's record. Archived ones are returned too (e.g. to unarchive them).
     */
    public function findOneByIdForUser(JournalEntryId $id, User $user): ?JournalEntryEntity
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * The user's journal entries for a project, newest first.
     *
     * By default only active entries are returned: archived entries, and every
     * entry under an archived project, product or company, are left out. Pass
     * $includeArchived = true for all.
     *
     * @return list<JournalEntryEntity>
     */
    public function getAllEntriesByProject(User $user, ProjectEntity $project, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('j')
            ->innerJoin('j.project', 'pj')
            ->innerJoin('pj.product', 'p')
            ->innerJoin('p.company', 'c')
            ->where('j.user = :user')
            ->andWhere('j.project = :project')
            ->setParameter('user', $user)
            ->setParameter('project', $project)
            ->orderBy('j.createdAt', 'DESC')
            ->addOrderBy('j.id', 'DESC');

        if (!$includeArchived) {
            $qb->andWhere('j.archivedAt IS NULL')
                ->andWhere('pj.archivedAt IS NULL')
                ->andWhere('p.archivedAt IS NULL')
                ->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }
}
