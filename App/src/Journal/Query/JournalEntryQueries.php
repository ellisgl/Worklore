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
use App\Taxonomy\Query\TagRefs;
use DateMalformedStringException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

use function array_column;

/**
 * Read side for journal entries: SQL shaped for the API, returning read
 * models. Tags come from one extra query per call (TagRefs), not one per
 * entry.
 *
 * @phpstan-type EntryRow array{id: string, project_id: string, project_name: string, title: string, entry: string, created_at: string, archived_at: string|null}
 */
final readonly class JournalEntryQueries
{
    use ReadsDatabaseTimes;

    /** Every column a JournalEntrySummary needs (but its tags), without FROM. */
    private const string COLUMNS = <<<'SQL'
        SELECT j.id,
               j.project_id,
               pj.name AS project_name,
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

    public function __construct(
        private Connection $db,
        private TagRefs $tagRefs,
        private MediaQueries $media,
    ) {
    }

    /**
     * Text search configuration: English stemming, so "deploying" finds
     * "deploy". The query side uses websearch syntax: "quoted phrases", or,
     * and -excluded words. The left side must stay identical to the
     * expression of idx_journal_entry_search (migration Version20261114100000),
     * or PostgreSQL can't use the index; JournalSearchIndexTest checks this.
     */
    public const string SEARCH = "to_tsvector('english', j.title || ' ' || j.entry) @@ websearch_to_tsquery('english', :search)";

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
    public function listForProject(
        UserId $ownerId,
        ProjectId $projectId,
        bool $includeArchived = false,
        Page $page = new Page(),
        JournalFilter $filter = new JournalFilter(),
    ): Paginated {
        return $this->list(
            ' WHERE j.user_id = :owner AND j.project_id = :project',
            ['owner' => $ownerId->toRfc4122(), 'project' => $projectId->toRfc4122()],
            $includeArchived,
            $page,
            $filter,
        );
    }

    /**
     * All the owner's journal entries, across projects, newest first. Leaves
     * out archived ones the same way as listForProject().
     *
     * @return Paginated<JournalEntrySummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function listForOwner(
        UserId $ownerId,
        bool $includeArchived = false,
        Page $page = new Page(),
        JournalFilter $filter = new JournalFilter(),
    ): Paginated {
        return $this->list(' WHERE j.user_id = :owner', ['owner' => $ownerId->toRfc4122()], $includeArchived, $page, $filter);
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
     * @param array<string, string> $params
     *
     * @return Paginated<JournalEntrySummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private function list(string $where, array $params, bool $includeArchived, Page $page, JournalFilter $filter): Paginated
    {
        if (!$includeArchived) {
            $where .= ' AND j.archived_at IS NULL AND pj.archived_at IS NULL AND p.archived_at IS NULL AND c.archived_at IS NULL';
        }
        if (null !== $filter->tagId) {
            $where .= ' AND EXISTS (SELECT 1 FROM journal_entry_tag jt WHERE jt.journal_entry_id = j.id AND jt.tag_id = :tag)';
            $params['tag'] = $filter->tagId->toRfc4122();
        }
        if (null !== $filter->search) {
            $where .= ' AND '.self::SEARCH;
            $params['search'] = $filter->search;
        }

        /** @var list<EntryRow> $rows */
        $rows = $this->db->fetchAllAssociative(self::SELECT.$where.' ORDER BY j.created_at DESC, j.id DESC'.PagedSql::limit($page), $params);

        return new Paginated($this->summaries($rows), $page, PagedSql::count($this->db, self::FROM.$where, $params));
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
        $tags = $this->tagRefs->forJournalEntries(array_column($rows, 'id'));
        $media = $this->media->forEntries(array_column($rows, 'id'));

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[] = new JournalEntrySummary(
                id: $row['id'],
                projectId: $row['project_id'],
                projectName: $row['project_name'],
                title: $row['title'],
                entry: $row['entry'],
                tags: $tags[$row['id']] ?? [],
                media: $media[$row['id']] ?? [],
                createdAt: self::utc($row['created_at']),
                archivedAt: self::utcOrNull($row['archived_at']),
            );
        }

        return $summaries;
    }
}
