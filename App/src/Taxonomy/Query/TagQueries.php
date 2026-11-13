<?php

declare(strict_types=1);

namespace App\Taxonomy\Query;

use App\Shared\Query\Page;
use App\Shared\Query\PagedSql;
use App\Shared\Query\Paginated;
use App\Shared\Query\ReadsDatabaseTimes;
use App\Shared\UserId;
use App\Taxonomy\CategoryId;
use App\Taxonomy\TagId;
use DateMalformedStringException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Read side for tags: SQL shaped for the API, returning read models.
 *
 * @phpstan-type TagRow array{id: string, category_id: string, category_name: string, name: string, created_at: string, archived_at: string|null}
 */
final readonly class TagQueries
{
    use ReadsDatabaseTimes;

    /** Every column a TagSummary needs, without FROM. */
    private const string COLUMNS = <<<'SQL'
        SELECT t.id,
               t.category_id,
               c.name AS category_name,
               t.name,
               t.created_at,
               t.archived_at
        SQL;

    /** The tables a TagSummary reads; callers add WHERE and ORDER BY. */
    private const string FROM = <<<'SQL'
          FROM tag t
          JOIN category c ON c.id = t.category_id
        SQL;

    private const string SELECT = self::COLUMNS."\n".self::FROM;

    public function __construct(private Connection $db)
    {
    }

    /**
     * All the owner's tags, sorted by category name and then tag name.
     * Same-named categories are ordered by creation, which keeps each one's
     * tags together.
     *
     * By default archived tags, and every tag in an archived category, are
     * left out; pass $includeArchived = true for all.
     *
     * @return Paginated<TagSummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function listForOwner(UserId $ownerId, bool $includeArchived = false, Page $page = new Page()): Paginated
    {
        return $this->list(
            ' WHERE t.user_id = :owner'.self::activeOnly($includeArchived),
            ['owner' => $ownerId->toRfc4122()],
            'c.name, c.id, t.name, t.id',
            $page,
        );
    }

    /**
     * The owner's tags in one category, sorted by name. Archived tags, or all
     * of them if the category is archived, are left out unless
     * $includeArchived.
     *
     * @return Paginated<TagSummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function listForCategory(UserId $ownerId, CategoryId $categoryId, bool $includeArchived = false, Page $page = new Page()): Paginated
    {
        return $this->list(
            ' WHERE t.user_id = :owner AND t.category_id = :category'.self::activeOnly($includeArchived),
            ['owner' => $ownerId->toRfc4122(), 'category' => $categoryId->toRfc4122()],
            't.name, t.id',
            $page,
        );
    }

    /**
     * One of the owner's tags, archived or not; null if it doesn't exist or
     * belongs to someone else.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function findForOwner(UserId $ownerId, TagId $tagId): ?TagSummary
    {
        /** @var TagRow|false $row */
        $row = $this->db->fetchAssociative(
            self::SELECT.' WHERE t.user_id = :owner AND t.id = :id',
            ['owner' => $ownerId->toRfc4122(), 'id' => $tagId->toRfc4122()],
        );

        return false === $row ? null : self::summary($row);
    }

    /**
     * @param array<string, string> $params
     *
     * @return Paginated<TagSummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private function list(string $where, array $params, string $orderBy, Page $page): Paginated
    {
        /** @var list<TagRow> $rows */
        $rows = $this->db->fetchAllAssociative(self::SELECT.$where.' ORDER BY '.$orderBy.PagedSql::limit($page), $params);

        $tags = [];
        foreach ($rows as $row) {
            $tags[] = self::summary($row);
        }

        return new Paginated($tags, $page, PagedSql::count($this->db, self::FROM.$where, $params));
    }

    private static function activeOnly(bool $includeArchived): string
    {
        return $includeArchived ? '' : ' AND t.archived_at IS NULL AND c.archived_at IS NULL';
    }

    /**
     * @param TagRow $row
     *
     * @throws DateMalformedStringException
     */
    private static function summary(array $row): TagSummary
    {
        return new TagSummary(
            id: $row['id'],
            categoryId: $row['category_id'],
            categoryName: $row['category_name'],
            name: $row['name'],
            createdAt: self::utc($row['created_at']),
            archivedAt: self::utcOrNull($row['archived_at']),
        );
    }
}
