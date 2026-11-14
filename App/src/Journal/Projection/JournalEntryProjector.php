<?php

declare(strict_types=1);

namespace App\Journal\Projection;

use App\Journal\Event\JournalEntryArchived;
use App\Journal\Event\JournalEntryMediaAttached;
use App\Journal\Event\JournalEntryMediaRemoved;
use App\Journal\Event\JournalEntryRetagged;
use App\Journal\Event\JournalEntryRetitled;
use App\Journal\Event\JournalEntryRewritten;
use App\Journal\Event\JournalEntryUnarchived;
use App\Journal\Event\JournalEntryWritten;
use App\Journal\JournalEvents;
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
        return ['journal_media', 'journal_entry_tag', 'journal_entry'];
    }

    public function forget(string $streamType, array $ids): void
    {
        if (JournalEvents::STREAM === $streamType) {
            self::deleteIn($this->db, 'journal_media', 'journal_entry_id', $ids);
            self::deleteIn($this->db, 'journal_entry_tag', 'journal_entry_id', $ids);
            self::deleteIn($this->db, 'journal_entry', 'id', $ids);
        }
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
            $e instanceof JournalEntryMediaAttached => $this->attached($e),
            $e instanceof JournalEntryMediaRemoved => $this->db->delete('journal_media', ['id' => $e->mediaId->toRfc4122()]),
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
     * The owner comes from the entry's row: media events don't repeat it.
     *
     * @throws Exception
     */
    private function attached(JournalEntryMediaAttached $e): void
    {
        $this->db->executeStatement(
            'INSERT INTO journal_media (id, journal_entry_id, user_id, filename, media_type, size, sha256, created_at)
             SELECT :id, j.id, j.user_id, :filename, :media_type, :size, :sha256, :created_at FROM journal_entry j WHERE j.id = :entry',
            [
                'id' => $e->mediaId->toRfc4122(),
                'entry' => $e->entryId->toRfc4122(),
                'filename' => $e->filename,
                'media_type' => $e->mediaType,
                'size' => $e->size,
                'sha256' => $e->sha256,
                'created_at' => self::time($e->attachedAt),
            ],
        );
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
