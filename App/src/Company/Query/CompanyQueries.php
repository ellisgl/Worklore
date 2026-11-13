<?php

declare(strict_types=1);

namespace App\Company\Query;

use App\Company\CompanyId;
use App\Shared\UserId;
use DateMalformedStringException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * The read side of the Company module: SQL shaped for what the API shows,
 * returning read models instead of entities. Writes go through commands
 * (App\Company\Application) and the aggregate.
 *
 * @phpstan-type CompanyRow array{id: string, name: string, overview: string|null, created_at: string, archived_at: string|null, product_count: int, current_position: string|null}
 */
final readonly class CompanyQueries
{
    use ReadsDatabaseTimes;

    public function __construct(private Connection $db)
    {
    }

    /** Every column a CompanySummary needs; callers add WHERE and ORDER BY. */
    private const string SELECT = <<<'SQL'
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
        SQL;

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
        $sql = self::SELECT.' WHERE c.user_id = :owner';
        if (!$includeArchived) {
            $sql .= ' AND c.archived_at IS NULL';
        }
        $sql .= ' ORDER BY c.name, c.id';

        /** @var list<CompanyRow> $rows */
        $rows = $this->db->fetchAllAssociative($sql, ['owner' => $ownerId->toRfc4122()]);

        $companies = [];
        foreach ($rows as $row) {
            $companies[] = self::summary($row);
        }

        return $companies;
    }

    /**
     * One of the owner's companies, archived or not; null if it doesn't exist
     * or belongs to someone else.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function findForOwner(UserId $ownerId, CompanyId $companyId): ?CompanySummary
    {
        /** @var CompanyRow|false $row */
        $row = $this->db->fetchAssociative(
            self::SELECT.' WHERE c.user_id = :owner AND c.id = :id',
            ['owner' => $ownerId->toRfc4122(), 'id' => $companyId->toRfc4122()],
        );

        return false === $row ? null : self::summary($row);
    }

    /**
     * Whether the company exists and belongs to the owner (archived or not).
     *
     * @throws Exception
     */
    public function existsForOwner(UserId $ownerId, CompanyId $companyId): bool
    {
        return false !== $this->db->fetchOne(
            'SELECT 1 FROM company WHERE user_id = :owner AND id = :id',
            ['owner' => $ownerId->toRfc4122(), 'id' => $companyId->toRfc4122()],
        );
    }

    /**
     * @param CompanyRow $row
     *
     * @throws DateMalformedStringException
     */
    private static function summary(array $row): CompanySummary
    {
        return new CompanySummary(
            id: $row['id'],
            name: $row['name'],
            overview: $row['overview'],
            createdAt: self::utc($row['created_at']),
            archivedAt: self::utcOrNull($row['archived_at']),
            productCount: (int) $row['product_count'],
            currentPosition: $row['current_position'],
        );
    }
}
