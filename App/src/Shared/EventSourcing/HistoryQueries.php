<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use App\Shared\EntityId;
use App\Shared\Query\Page;
use App\Shared\Query\PagedSql;
use App\Shared\Query\Paginated;
use App\Shared\Query\ReadsDatabaseTimes;
use App\Shared\UserId;
use DateMalformedStringException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use JsonException;
use UnexpectedValueException;

use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Read side for histories: a record's events straight from the event store,
 * oldest first.
 *
 * @phpstan-type HistoryRow array{stream_version: int|string, event_type: string, recorded_at: string, payload: string, impersonator_id: string|null}
 */
final readonly class HistoryQueries
{
    use ReadsDatabaseTimes;

    public function __construct(private Connection $db)
    {
    }

    /**
     * The owner's record's events, or null if there is no such record of
     * theirs (unknown, or someone else's: the two look the same).
     *
     * @return Paginated<HistoryEntry>|null
     *
     * @throws Exception
     */
    public function forOwner(UserId $ownerId, string $streamType, EntityId $id, Page $page = new Page()): ?Paginated
    {
        $where = ' FROM event_store WHERE stream_type = :type AND stream_id = :id AND user_id = :owner';
        $params = ['type' => $streamType, 'id' => $id->toRfc4122(), 'owner' => $ownerId->toRfc4122()];

        $total = PagedSql::count($this->db, $where, $params);
        if (0 === $total) {
            return null;
        }

        /** @var list<HistoryRow> $rows */
        $rows = $this->db->fetchAllAssociative('SELECT stream_version, event_type, recorded_at, payload, impersonator_id'.$where.' ORDER BY stream_version'.PagedSql::limit($page), $params);

        $entries = [];
        foreach ($rows as $row) {
            $entries[] = self::entry($row);
        }

        return new Paginated($entries, $page, $total);
    }

    /**
     * @param HistoryRow $row
     */
    private static function entry(array $row): HistoryEntry
    {
        try {
            $data = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
            $recordedAt = self::utc($row['recorded_at']);
        } catch (JsonException|DateMalformedStringException $e) {
            throw new UnexpectedValueException('Unreadable event in the event store.', previous: $e);
        }
        if (!is_array($data)) {
            throw new UnexpectedValueException('Stored payload is not a JSON object.');
        }
        /** @var array<string, mixed> $payload */
        $payload = $data;

        return new HistoryEntry((int) $row['stream_version'], $row['event_type'], $recordedAt, $payload, $row['impersonator_id']);
    }
}
