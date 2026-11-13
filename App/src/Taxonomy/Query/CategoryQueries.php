<?php

declare(strict_types=1);

namespace App\Taxonomy\Query;

use App\Shared\Query\Page;
use App\Shared\Query\PagedSql;
use App\Shared\Query\Paginated;
use App\Shared\Query\ReadsDatabaseTimes;
use App\Shared\UserId;
use App\Taxonomy\CategoryId;
use DateMalformedStringException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Read side for categories: SQL shaped for the API, returning read models.
 *
 * @phpstan-type CategoryRow array{id: string, name: string, description: string|null, created_at: string, archived_at: string|null, tag_count: int}
 */
final readonly class CategoryQueries
{
    use ReadsDatabaseTimes;

    /** Every column a CategorySummary needs, without FROM. */
    private const string COLUMNS = <<<'SQL'
        SELECT c.id,
               c.name,
               c.description,
               c.created_at,
               c.archived_at,
               (SELECT count(*)
                  FROM tag t
                 WHERE t.category_id = c.id
                   AND t.archived_at IS NULL) AS tag_count
        SQL;

    /** The tables a CategorySummary reads; callers add WHERE and ORDER BY. */
    private const string FROM = <<<'SQL'
          FROM category c
        SQL;

    private const string SELECT = self::COLUMNS."\n".self::FROM;

    public function __construct(private Connection $db)
    {
    }

    /**
     * The owner's categories, sorted by name (same names in creation order).
     *
     * By default archived categories are left out; pass $includeArchived =
     * true for all.
     *
     * @return Paginated<CategorySummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function listForOwner(UserId $ownerId, bool $includeArchived = false, Page $page = new Page()): Paginated
    {
        $where = ' WHERE c.user_id = :owner'.($includeArchived ? '' : ' AND c.archived_at IS NULL');
        $params = ['owner' => $ownerId->toRfc4122()];

        /** @var list<CategoryRow> $rows */
        $rows = $this->db->fetchAllAssociative(self::SELECT.$where.' ORDER BY c.name, c.id'.PagedSql::limit($page), $params);

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = self::summary($row);
        }

        return new Paginated($categories, $page, PagedSql::count($this->db, self::FROM.$where, $params));
    }

    /**
     * One of the owner's categories, archived or not; null if it doesn't
     * exist or belongs to someone else.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function findForOwner(UserId $ownerId, CategoryId $categoryId): ?CategorySummary
    {
        /** @var CategoryRow|false $row */
        $row = $this->db->fetchAssociative(
            self::SELECT.' WHERE c.user_id = :owner AND c.id = :id',
            ['owner' => $ownerId->toRfc4122(), 'id' => $categoryId->toRfc4122()],
        );

        return false === $row ? null : self::summary($row);
    }

    /**
     * Whether the category exists and belongs to the owner (archived or not).
     *
     * @throws Exception
     */
    public function existsForOwner(UserId $ownerId, CategoryId $categoryId): bool
    {
        return false !== $this->db->fetchOne(
            'SELECT 1 FROM category WHERE user_id = :owner AND id = :id',
            ['owner' => $ownerId->toRfc4122(), 'id' => $categoryId->toRfc4122()],
        );
    }

    /**
     * @param CategoryRow $row
     *
     * @throws DateMalformedStringException
     */
    private static function summary(array $row): CategorySummary
    {
        return new CategorySummary(
            id: $row['id'],
            name: $row['name'],
            description: $row['description'],
            createdAt: self::utc($row['created_at']),
            archivedAt: self::utcOrNull($row['archived_at']),
            tagCount: (int) $row['tag_count'],
        );
    }
}
