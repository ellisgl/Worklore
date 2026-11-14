<?php

declare(strict_types=1);

namespace App\Journal\Query;

use App\Journal\JournalEntryId;
use App\Journal\MediaId;
use App\Shared\Query\ReadsDatabaseTimes;
use App\Shared\UserId;
use DateMalformedStringException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

use function is_numeric;

/**
 * Reads the journal_media projection.
 *
 * @phpstan-type MediaRow array{id: string, journal_entry_id: string, filename: string, media_type: string, size: int|string, sha256: string, created_at: string}
 */
final readonly class MediaQueries
{
    use ReadsDatabaseTimes;

    private const string SELECT = 'SELECT id, journal_entry_id, filename, media_type, size, sha256, created_at FROM journal_media';

    public function __construct(private Connection $db)
    {
    }

    /**
     * Bytes of media the user stores.
     *
     * @throws Exception
     */
    public function usedBy(UserId $ownerId): int
    {
        $used = $this->db->fetchOne('SELECT COALESCE(SUM(size), 0) FROM journal_media WHERE user_id = :owner', ['owner' => $ownerId->toRfc4122()]);

        return is_numeric($used) ? (int) $used : 0;
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function find(UserId $ownerId, JournalEntryId $entryId, MediaId $mediaId): ?MediaSummary
    {
        /** @var MediaRow|false $row */
        $row = $this->db->fetchAssociative(
            self::SELECT.' WHERE user_id = :owner AND journal_entry_id = :entry AND id = :id',
            ['owner' => $ownerId->toRfc4122(), 'entry' => $entryId->toRfc4122(), 'id' => $mediaId->toRfc4122()],
        );

        return false === $row ? null : self::summary($row);
    }

    /**
     * Each entry's media, oldest first.
     *
     * @param list<string> $entryIds
     *
     * @return array<string, list<MediaSummary>> by entry id; entries without media are missing
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function forEntries(array $entryIds): array
    {
        if ([] === $entryIds) {
            return [];
        }
        /** @var list<MediaRow> $rows */
        $rows = $this->db->fetchAllAssociative(
            self::SELECT.' WHERE journal_entry_id IN (:ids) ORDER BY created_at, id',
            ['ids' => $entryIds],
            ['ids' => ArrayParameterType::STRING],
        );

        $media = [];
        foreach ($rows as $row) {
            $media[$row['journal_entry_id']][] = self::summary($row);
        }

        return $media;
    }

    /**
     * Which of these media ids are attached to something.
     *
     * @param list<string> $mediaIds
     *
     * @return array<string, true>
     *
     * @throws Exception
     */
    public function existing(array $mediaIds): array
    {
        if ([] === $mediaIds) {
            return [];
        }
        $existing = [];
        /** @var list<string> $ids */
        $ids = $this->db->fetchFirstColumn('SELECT id FROM journal_media WHERE id IN (:ids)', ['ids' => $mediaIds], ['ids' => ArrayParameterType::STRING]);
        foreach ($ids as $id) {
            $existing[$id] = true;
        }

        return $existing;
    }

    /**
     * @param MediaRow $row
     *
     * @throws DateMalformedStringException
     */
    private static function summary(array $row): MediaSummary
    {
        return new MediaSummary($row['id'], $row['filename'], $row['media_type'], (int) $row['size'], $row['sha256'], self::utc($row['created_at']));
    }
}
