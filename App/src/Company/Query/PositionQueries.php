<?php

declare(strict_types=1);

namespace App\Company\Query;

use App\Company\CompanyId;
use App\Company\PositionId;
use App\Shared\UserId;
use DateMalformedStringException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Read side for positions: SQL shaped for the API, returning read models.
 *
 * @phpstan-type PositionRow array{id: string, company_id: string, title: string, overview: string|null, start_date: string, end_date: string|null, reason_left: string|null, created_at: string, archived_at: string|null}
 */
final readonly class PositionQueries
{
    use ReadsDatabaseTimes;

    /** Every column a PositionSummary needs; callers add WHERE and ORDER BY. */
    private const string SELECT = <<<'SQL'
        SELECT po.id,
               po.company_id,
               po.title,
               po.overview,
               po.start_date,
               po.end_date,
               po.reason_left,
               po.created_at,
               po.archived_at
          FROM "position" po
          JOIN company c ON c.id = po.company_id
        SQL;

    public function __construct(private Connection $db)
    {
    }

    /**
     * The owner's positions at a company, newest start date first.
     *
     * By default archived positions, and every position at an archived
     * company, are left out; pass $includeArchived = true for all.
     *
     * @return list<PositionSummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function listForCompany(UserId $ownerId, CompanyId $companyId, bool $includeArchived = false): array
    {
        $sql = self::SELECT.' WHERE po.user_id = :owner AND po.company_id = :company';
        if (!$includeArchived) {
            $sql .= ' AND po.archived_at IS NULL AND c.archived_at IS NULL';
        }
        $sql .= ' ORDER BY po.start_date DESC, po.id DESC';

        /** @var list<PositionRow> $rows */
        $rows = $this->db->fetchAllAssociative($sql, ['owner' => $ownerId->toRfc4122(), 'company' => $companyId->toRfc4122()]);

        $positions = [];
        foreach ($rows as $row) {
            $positions[] = self::summary($row);
        }

        return $positions;
    }

    /**
     * The owner's current positions (no end date) across all companies,
     * newest start date first. Archived positions and positions at archived
     * companies are always left out.
     *
     * @return list<PositionSummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function listCurrentForOwner(UserId $ownerId): array
    {
        /** @var list<PositionRow> $rows */
        $rows = $this->db->fetchAllAssociative(
            self::SELECT.' WHERE po.user_id = :owner AND po.end_date IS NULL AND po.archived_at IS NULL AND c.archived_at IS NULL'
                .' ORDER BY po.start_date DESC, po.id DESC',
            ['owner' => $ownerId->toRfc4122()],
        );

        $positions = [];
        foreach ($rows as $row) {
            $positions[] = self::summary($row);
        }

        return $positions;
    }

    /**
     * One of the owner's positions, archived or not; null if it doesn't exist
     * or belongs to someone else.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function findForOwner(UserId $ownerId, PositionId $positionId): ?PositionSummary
    {
        /** @var PositionRow|false $row */
        $row = $this->db->fetchAssociative(
            self::SELECT.' WHERE po.user_id = :owner AND po.id = :id',
            ['owner' => $ownerId->toRfc4122(), 'id' => $positionId->toRfc4122()],
        );

        return false === $row ? null : self::summary($row);
    }

    /**
     * @param PositionRow $row
     *
     * @throws DateMalformedStringException
     */
    private static function summary(array $row): PositionSummary
    {
        return new PositionSummary(
            id: $row['id'],
            companyId: $row['company_id'],
            title: $row['title'],
            overview: $row['overview'],
            startDate: $row['start_date'],
            endDate: $row['end_date'],
            current: null === $row['end_date'],
            reasonLeft: $row['reason_left'],
            createdAt: self::utc($row['created_at']),
            archivedAt: self::utcOrNull($row['archived_at']),
        );
    }
}
