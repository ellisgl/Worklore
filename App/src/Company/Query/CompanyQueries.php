<?php

declare(strict_types=1);

namespace App\Company\Query;

use App\Shared\UserId;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * The read side of the Company module: SQL shaped for what the API shows,
 * returning read models instead of entities. Writes go through commands
 * (App\Company\Application) and the aggregate.
 */
final readonly class CompanyQueries
{
    public function __construct(private Connection $db)
    {
    }

    /**
     * The owner's companies, sorted by name.
     *
     * By default archived companies are left out; pass $includeArchived =
     * true for all.
     *
     * @return list<CompanySummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function listForOwner(UserId $ownerId, bool $includeArchived = false): array
    {
        $sql = <<<'SQL'
            SELECT c.id,
                   c.name,
                   c.overview,
                   c.created_at,
                   c.archived_at,
                   (SELECT count(*)
                      FROM product p
                     WHERE p.company_id = c.id
                       AND p.archived_at IS NULL) AS product_count,
                   (SELECT po.title
                      FROM "position" po
                     WHERE po.company_id = c.id
                       AND po.end_date IS NULL
                       AND po.archived_at IS NULL
                     ORDER BY po.start_date DESC, po.id DESC
                     LIMIT 1) AS current_position
              FROM company c
             WHERE c.user_id = :owner
            SQL;

        if (!$includeArchived) {
            $sql .= "\n               AND c.archived_at IS NULL";
        }
        $sql .= "\n             ORDER BY c.name, c.id";

        $rows = $this->db->fetchAllAssociative($sql, ['owner' => $ownerId->toRfc4122()]);

        return array_map(static fn (array $row) => new CompanySummary(
            id: $row['id'],
            name: $row['name'],
            overview: $row['overview'],
            createdAt: self::utc($row['created_at']),
            archivedAt: null === $row['archived_at'] ? null : self::utc($row['archived_at']),
            productCount: (int) $row['product_count'],
            currentPosition: $row['current_position'],
        ), $rows);
    }

    /**
     * Timestamps are stored in UTC without a zone.
     *
     * @throws DateMalformedStringException
     */
    private static function utc(string $timestamp): DateTimeImmutable
    {
        return new DateTimeImmutable($timestamp, new DateTimeZone('UTC'));
    }
}
