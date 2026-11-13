<?php

declare(strict_types=1);

namespace App\Journal\Query;

use App\Company\ProjectId;
use App\Journal\JournalEntryId;
use App\Shared\Query\Page;
use App\Shared\Query\PagedSql;
use App\Shared\Query\Paginated;
use App\Shared\Query\ReadsDatabaseTimes;
use App\Shared\UserId;
use DateMalformedStringException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

use function array_column;

/**
 * Read side for journal entries: SQL shaped for the API, returning read
 * models. Tags come from one extra query per call, not one per entry.
 *
 * @phpstan-type EntryRow array{id: string, project_id: string, title: string, entry: string, created_at: string, archived_at: string|null}
 * @phpstan-type TagRow array{journal_entry_id: string, id: string, name: string, category_id: string, category_name: string}
 */
final readonly class JournalEntryQueries
{
    use ReadsDatabaseTimes;

    /** Every column a JournalEntrySummary needs (but its tags), without FROM. */
    private const string COLUMNS = <<<'SQL'
        SELECT j.id,
               j.project_id,
               j.title,
               j.entry,
               j.created_at,
               j.archived_at
        SQL;

    /** The tables an entry reads; callers add WHERE and ORDER BY. */
    private const string FROM = <<<'SQL'
          FROM journal_entry j
          JOIN project pj ON pj.id = j.project_id
          JOIN product p ON p.id = pj.product_id
          JOIN company c ON c.id = p.company_id
        SQL;

    private const string SELECT = self::COLUMNS."\n".self::FROM;

    public function __construct(private Connection $db)
    {
    }

    /**
     * The owner's journal entries for a project, newest first.
     *
     * By default archived entries, and every entry under an archived project,
     * product or company, are left out; pass $includeArchived = true for all.
     *
     * @return Paginated<JournalEntrySummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function listForProject(UserId $ownerId, ProjectId $projectId, bool $includeArchived = false, Page $page = new Page()): Paginated
    {
        $where = ' WHERE j.user_id = :owner AND j.project_id = :project'
            .($includeArchived ? '' : ' AND j.archived_at IS NULL AND pj.archived_at IS NULL AND p.archived_at IS NULL AND c.archived_at IS NULL');
        $params = ['owner' => $ownerId->toRfc4122(), 'project' => $projectId->toRfc4122()];

        /** @var list<EntryRow> $rows */
        $rows = $this->db->fetchAllAssociative(self::SELECT.$where.' ORDER BY j.created_at DESC, j.id DESC'.PagedSql::limit($page), $params);

        return new Paginated($this->summaries($rows), $page, PagedSql::count($this->db, self::FROM.$where, $params));
    }

    /**
     * One of the owner's journal entries, archived or not; null if it doesn't
     * exist or belongs to someone else.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function findForOwner(UserId $ownerId, JournalEntryId $entryId): ?JournalEntrySummary
    {
        /** @var EntryRow|false $row */
        $row = $this->db->fetchAssociative(
            self::SELECT.' WHERE j.user_id = :owner AND j.id = :id',
            ['owner' => $ownerId->toRfc4122(), 'id' => $entryId->toRfc4122()],
        );

        return false === $row ? null : $this->summaries([$row])[0];
    }

    /**
     * @param list<EntryRow> $rows
     *
     * @return list<JournalEntrySummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private function summaries(array $rows): array
    {
        $tags = $this->tagsByEntry(array_column($rows, 'id'));

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[] = new JournalEntrySummary(
                id: $row['id'],
                projectId: $row['project_id'],
                title: $row['title'],
                entry: $row['entry'],
                tags: $tags[$row['id']] ?? [],
                createdAt: self::utc($row['created_at']),
                archivedAt: self::utcOrNull($row['archived_at']),
            );
        }

        return $summaries;
    }

    /**
     * The tags of these entries, archived ones included (they are still on
     * the entry), sorted by category and then name.
     *
     * @param list<string> $entryIds
     *
     * @return array<string, list<JournalTag>> keyed by entry id
     *
     * @throws Exception
     */
    private function tagsByEntry(array $entryIds): array
    {
        if ([] === $entryIds) {
            return [];
        }

        /** @var list<TagRow> $rows */
        $rows = $this->db->fetchAllAssociative(
            <<<'SQL'
                SELECT jt.journal_entry_id,
                       t.id,
                       t.name,
                       t.category_id,
                       c.name AS category_name
                  FROM journal_entry_tag jt
                  JOIN tag t ON t.id = jt.tag_id
                  JOIN category c ON c.id = t.category_id
                 WHERE jt.journal_entry_id IN (:ids)
                 ORDER BY c.name, c.id, t.name, t.id
                SQL,
            ['ids' => $entryIds],
            ['ids' => ArrayParameterType::STRING],
        );

        $tags = [];
        foreach ($rows as $row) {
            $tags[$row['journal_entry_id']][] = new JournalTag($row['id'], $row['name'], $row['category_id'], $row['category_name']);
        }

        return $tags;
    }
}
