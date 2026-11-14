<?php

declare(strict_types=1);

namespace App\Export;

use App\Shared\EventSourcing\EventStore;
use App\Shared\Query\ReadsDatabaseTimes;
use App\Shared\UserId;
use DateMalformedStringException;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;

use function array_column;
use function is_numeric;
use function is_string;

/**
 * Builds a user's DataExport: the records from the read tables, archived
 * ones included, oldest first; and every event behind them from the event
 * store, in the order they happened. Timestamps become ISO 8601 in UTC;
 * dates stay YYYY-MM-DD. Password hashes and tokens are never exported.
 */
final readonly class DataExporter
{
    use ReadsDatabaseTimes;

    public function __construct(
        private Connection $db,
        private EventStore $events,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function export(UserId $ownerId): DataExport
    {
        $owner = ['owner' => $ownerId->toRfc4122()];
        $account = $this->rows('SELECT id, email, created_at, archived_at FROM app_user WHERE id = :owner', $owner);
        $projects = $this->rows('SELECT id, product_id, name, overview, created_at, archived_at FROM project WHERE user_id = :owner ORDER BY created_at, id', $owner);
        $entries = $this->rows('SELECT id, project_id, title, entry, created_at, archived_at FROM journal_entry WHERE user_id = :owner ORDER BY created_at, id', $owner);

        return new DataExport(
            exportedAt: $this->clock->now(),
            account: $account[0] ?? [],
            companies: $this->rows('SELECT id, name, overview, created_at, archived_at FROM company WHERE user_id = :owner ORDER BY created_at, id', $owner),
            products: $this->rows('SELECT id, company_id, name, overview, created_at, archived_at FROM product WHERE user_id = :owner ORDER BY created_at, id', $owner),
            projects: $this->withTagIds($projects, 'project_tag', 'project_id'),
            positions: $this->rows('SELECT id, company_id, title, overview, start_date, end_date, reason_left, created_at, archived_at FROM "position" WHERE user_id = :owner ORDER BY created_at, id', $owner),
            categories: $this->rows('SELECT id, name, description, created_at, archived_at FROM category WHERE user_id = :owner ORDER BY created_at, id', $owner),
            tags: $this->rows('SELECT id, category_id, name, created_at, archived_at FROM tag WHERE user_id = :owner ORDER BY created_at, id', $owner),
            journalEntries: $this->withTagIds($entries, 'journal_entry_tag', 'journal_entry_id'),
            media: $this->media($owner),
            events: $this->events($ownerId),
        );
    }

    /**
     * Files attached to journal entries; their bytes are in the archive
     * export (GET /api/me/export/archive) under media/{id}.
     *
     * @param array<string, string> $owner
     *
     * @return list<array<string, mixed>>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private function media(array $owner): array
    {
        $rows = [];
        foreach ($this->rows('SELECT id, journal_entry_id, filename, media_type, size, sha256, created_at FROM journal_media WHERE user_id = :owner ORDER BY created_at, id', $owner) as $row) {
            unset($row['archived_at']);
            $size = $row['size'] ?? null;
            $row['size'] = is_numeric($size) ? (int) $size : 0;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    private function events(UserId $ownerId): array
    {
        $events = [];
        foreach ($this->events->ownedBy($ownerId) as $event) {
            $events[] = [
                'stream_type' => $event->streamType,
                'stream_id' => $event->streamId->toRfc4122(),
                'version' => $event->version,
                'type' => $event->type,
                'recorded_at' => $event->recordedAt->format(DateTimeInterface::ATOM),
                'data' => $event->payload,
            ];
        }

        return $events;
    }

    /**
     * Rows with created_at/archived_at as ISO 8601 UTC strings.
     *
     * @param array<string, string> $params
     *
     * @return list<array<string, mixed>>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private function rows(string $sql, array $params): array
    {
        $rows = [];
        foreach ($this->db->fetchAllAssociative($sql, $params) as $row) {
            foreach (['created_at', 'archived_at'] as $column) {
                $value = $row[$column] ?? null;
                $row[$column] = is_string($value) ? self::utc($value)->format(DateTimeInterface::ATOM) : null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Adds each row's tag ids (sorted) from a join table.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    private function withTagIds(array $rows, string $joinTable, string $column): array
    {
        $ids = array_column($rows, 'id');
        $tagIds = [];
        if ([] !== $ids) {
            /** @var list<array{owner_id: string, tag_id: string}> $links */
            $links = $this->db->fetchAllAssociative(
                "SELECT $column AS owner_id, tag_id FROM $joinTable WHERE $column IN (:ids) ORDER BY tag_id",
                ['ids' => $ids],
                ['ids' => ArrayParameterType::STRING],
            );
            foreach ($links as $link) {
                $tagIds[$link['owner_id']][] = $link['tag_id'];
            }
        }

        foreach ($rows as $i => $row) {
            $id = $row['id'] ?? null;
            $rows[$i]['tag_ids'] = is_string($id) ? $tagIds[$id] ?? [] : [];
        }

        return $rows;
    }
}
