<?php

declare(strict_types=1);

namespace App\Journal;

use App\Shared\Owner;
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
     * Saves a new journal entry on the next flush.
     */
    public function add(JournalEntryEntity $entry): void
    {
        $this->getEntityManager()->persist($entry);
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
}
