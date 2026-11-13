<?php

declare(strict_types=1);

namespace App\Taxonomy\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Loads the tags of many records in one query, for the read models of
 * records that carry tags. Archived tags are included (they are still on the
 * record); each record's tags are sorted by category and then name.
 *
 * @phpstan-type TagRow array{record_id: string, id: string, name: string, category_id: string, category_name: string}
 */
final readonly class TagRefs
{
    public function __construct(private Connection $db)
    {
    }

    /**
     * @param list<string> $entryIds
     *
     * @return array<string, list<TagRef>> keyed by entry id; untagged entries are absent
     *
     * @throws Exception
     */
    public function forJournalEntries(array $entryIds): array
    {
        return $this->load('journal_entry_tag', 'journal_entry_id', $entryIds);
    }

    /**
     * @param list<string> $projectIds
     *
     * @return array<string, list<TagRef>> keyed by project id; untagged projects are absent
     *
     * @throws Exception
     */
    public function forProjects(array $projectIds): array
    {
        return $this->load('project_tag', 'project_id', $projectIds);
    }

    /**
     * @param string       $joinTable a join table with $column and tag_id; never user input
     * @param list<string> $ids
     *
     * @return array<string, list<TagRef>>
     *
     * @throws Exception
     */
    private function load(string $joinTable, string $column, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<TagRow> $rows */
        $rows = $this->db->fetchAllAssociative(
            <<<SQL
                SELECT jt.$column AS record_id,
                       t.id,
                       t.name,
                       t.category_id,
                       c.name AS category_name
                  FROM $joinTable jt
                  JOIN tag t ON t.id = jt.tag_id
                  JOIN category c ON c.id = t.category_id
                 WHERE jt.$column IN (:ids)
                 ORDER BY c.name, c.id, t.name, t.id
                SQL,
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        $tags = [];
        foreach ($rows as $row) {
            $tags[$row['record_id']][] = new TagRef($row['id'], $row['name'], $row['category_id'], $row['category_name']);
        }

        return $tags;
    }
}
