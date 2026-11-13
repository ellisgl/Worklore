<?php

declare(strict_types=1);

namespace App\Company\Query;

use App\Company\ProductId;
use App\Company\ProjectId;
use App\Shared\Query\Page;
use App\Shared\Query\PagedSql;
use App\Shared\Query\Paginated;
use App\Shared\UserId;
use DateMalformedStringException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Read side for projects: SQL shaped for the API, returning read models.
 *
 * @phpstan-type ProjectRow array{id: string, product_id: string, company_id: string, name: string, overview: string|null, created_at: string, archived_at: string|null}
 */
final readonly class ProjectQueries
{
    use ReadsDatabaseTimes;

    /** Every column a ProjectSummary needs, without FROM. */
    private const string COLUMNS = <<<'SQL'
        SELECT pj.id,
               pj.product_id,
               p.company_id,
               pj.name,
               pj.overview,
               pj.created_at,
               pj.archived_at
        SQL;

    /** The tables a ProjectSummary reads; callers add WHERE and ORDER BY. */
    private const string FROM = <<<'SQL'
          FROM project pj
          JOIN product p ON p.id = pj.product_id
          JOIN company c ON c.id = p.company_id
        SQL;

    private const string SELECT = self::COLUMNS."\n".self::FROM;

    public function __construct(private Connection $db)
    {
    }

    /**
     * The owner's projects for a product, sorted by name.
     *
     * By default archived projects, and every project under an archived
     * product or company, are left out; pass $includeArchived = true for all.
     *
     * @return Paginated<ProjectSummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function listForProduct(UserId $ownerId, ProductId $productId, bool $includeArchived = false, Page $page = new Page()): Paginated
    {
        $where = ' WHERE pj.user_id = :owner AND pj.product_id = :product'
            .($includeArchived ? '' : ' AND pj.archived_at IS NULL AND p.archived_at IS NULL AND c.archived_at IS NULL');
        $params = ['owner' => $ownerId->toRfc4122(), 'product' => $productId->toRfc4122()];

        /** @var list<ProjectRow> $rows */
        $rows = $this->db->fetchAllAssociative(self::SELECT.$where.' ORDER BY pj.name, pj.id'.PagedSql::limit($page), $params);

        $projects = [];
        foreach ($rows as $row) {
            $projects[] = self::summary($row);
        }

        return new Paginated($projects, $page, PagedSql::count($this->db, self::FROM.$where, $params));
    }

    /**
     * One of the owner's projects, archived or not; null if it doesn't exist
     * or belongs to someone else.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function findForOwner(UserId $ownerId, ProjectId $projectId): ?ProjectSummary
    {
        /** @var ProjectRow|false $row */
        $row = $this->db->fetchAssociative(
            self::SELECT.' WHERE pj.user_id = :owner AND pj.id = :id',
            ['owner' => $ownerId->toRfc4122(), 'id' => $projectId->toRfc4122()],
        );

        return false === $row ? null : self::summary($row);
    }

    /**
     * @param ProjectRow $row
     *
     * @throws DateMalformedStringException
     */
    private static function summary(array $row): ProjectSummary
    {
        return new ProjectSummary(
            id: $row['id'],
            productId: $row['product_id'],
            companyId: $row['company_id'],
            name: $row['name'],
            overview: $row['overview'],
            createdAt: self::utc($row['created_at']),
            archivedAt: self::utcOrNull($row['archived_at']),
        );
    }
}
