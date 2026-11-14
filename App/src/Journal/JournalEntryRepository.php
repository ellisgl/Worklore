<?php

declare(strict_types=1);

namespace App\Journal;

use App\Shared\EventSourcing\AggregateStore;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\UserId;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Journal entries, loaded from and saved to their event streams.
 */
final readonly class JournalEntryRepository
{
    public function __construct(private AggregateStore $store)
    {
    }

    /**
     * The entry, if it exists and belongs to the owner; null otherwise, so
     * an endpoint answers 404 instead of revealing someone else's record.
     * Archived entries are returned too.
     *
     * @throws Exception
     */
    public function findForOwner(JournalEntryId $id, UserId $ownerId): ?JournalEntry
    {
        $history = $this->store->history(JournalEvents::STREAM, $id);
        if ([] === $history) {
            return null;
        }
        $entry = JournalEntry::fromHistory($history);

        return $entry->ownerId()->equals($ownerId) ? $entry : null;
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function save(JournalEntry $entry): void
    {
        $this->store->save(JournalEvents::STREAM, $entry);
    }
}
