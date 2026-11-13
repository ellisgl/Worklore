<?php

declare(strict_types=1);

namespace App\Journal;

use App\Shared\Owner;
use App\Company\ProjectEntity;
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
    public function findOneByIdForOwner(JournalEntryId $id, Owner $owner): ?JournalEntryEntity
    {
        return $this->findOneBy(['id' => $id, 'owner' => $owner]);
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
    public function getAllEntriesByProject(Owner $owner, ProjectEntity $project, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('j')
            ->innerJoin('j.project', 'pj')
            ->innerJoin('pj.product', 'p')
            ->innerJoin('p.company', 'c')
            ->where('j.owner = :owner')
            ->andWhere('j.project = :project')
            ->setParameter('owner', $owner)
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
