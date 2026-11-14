<?php

declare(strict_types=1);

namespace App\Journal\Projection;

use App\Journal\Event\JournalEntryArchived;
use App\Journal\Event\JournalEntryRetagged;
use App\Journal\Event\JournalEntryRetitled;
use App\Journal\Event\JournalEntryRewritten;
use App\Journal\Event\JournalEntryUnarchived;
use App\Journal\Event\JournalEntryWritten;
use App\Shared\EventSourcing\Projector;
use App\Shared\EventSourcing\StoredEvent;
use App\Shared\EventSourcing\WritesRows;
use App\Taxonomy\TagId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Projects journal entry events into journal_entry and journal_entry_tag,
 * the tables JournalEntryQueries reads.
 */
final readonly class JournalEntryProjector implements Projector
{
    use WritesRows;

    public function __construct(private Connection $db)
    {
    }

    public function tables(): array
    {
        return ['journal_entry_tag', 'journal_entry'];
    }

    public function project(StoredEvent $event): void
    {
        $e = $event->event;
        match (true) {
            $e instanceof JournalEntryWritten => $this->written($e),
            $e instanceof JournalEntryRetitled => $this->update($e->entryId->toRfc4122(), ['title' => $e->title]),
            $e instanceof JournalEntryRewritten => $this->update($e->entryId->toRfc4122(), ['entry' => $e->entry]),
            $e instanceof JournalEntryRetagged => $this->tag($e->entryId->toRfc4122(), $e->tagIds),
            $e instanceof JournalEntryArchived => $this->update($e->entryId->toRfc4122(), ['archived_at' => self::time($e->archivedAt)]),
            $e instanceof JournalEntryUnarchived => $this->update($e->entryId->toRfc4122(), ['archived_at' => null]),
            default => null,
        };
    }

    /**
     * @throws Exception
     */
    private function written(JournalEntryWritten $e): void
    {
        $this->db->insert('journal_entry', [
            'id' => $e->entryId->toRfc4122(),
            'project_id' => $e->projectId->toRfc4122(),
            'user_id' => $e->ownerId->toRfc4122(),
            'title' => $e->title,
            'entry' => $e->entry,
            'created_at' => self::time($e->writtenAt),
            'archived_at' => null,
        ]);
        $this->tag($e->entryId->toRfc4122(), $e->tagIds);
    }

    /**
     * @param array<string, string|null> $columns
     *
     * @throws Exception
     */
    private function update(string $id, array $columns): void
    {
        $this->db->update('journal_entry', $columns, ['id' => $id]);
    }

    /**
     * @param list<TagId> $tagIds
     *
     * @throws Exception
     */
    private function tag(string $id, array $tagIds): void
    {
        $this->db->delete('journal_entry_tag', ['journal_entry_id' => $id]);
        foreach ($tagIds as $tagId) {
            $this->db->insert('journal_entry_tag', ['journal_entry_id' => $id, 'tag_id' => $tagId->toRfc4122()]);
        }
    }
}
