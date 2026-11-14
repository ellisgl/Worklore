<?php

declare(strict_types=1);

namespace App\Purge;

use App\Shared\UserId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

use function array_diff;
use function array_values;

/**
 * Works out a PurgePlan from the read tables, only ever among the owner's
 * records, archived ones included:
 *
 *     company   → its products and positions
 *     product   → its projects
 *     project   → its journal entries
 *     entry     → its media files
 *     category  → its tags
 *     tag       → untagged from the projects and entries that keep living
 */
final readonly class PurgePlanner
{
    public function __construct(private Connection $db)
    {
    }

    /**
     * Null if the owner has no such record.
     *
     * @throws Exception
     */
    public function plan(UserId $ownerId, PurgeTarget $target, string $id): ?PurgePlan
    {
        $owner = $ownerId->toRfc4122();
        if (false === $this->db->fetchOne("SELECT 1 FROM {$target->table()} WHERE id = :id AND user_id = :owner", ['id' => $id, 'owner' => $owner])) {
            return null;
        }

        $records = [];
        foreach (PurgeTarget::cases() as $case) {
            $records[$case->value] = [];
        }
        $records[$target->value] = [$id];

        $records[PurgeTarget::Products->value] = [...$records[PurgeTarget::Products->value], ...$this->children('product', 'company_id', $records[PurgeTarget::Companies->value], $owner)];
        $records[PurgeTarget::Positions->value] = [...$records[PurgeTarget::Positions->value], ...$this->children('"position"', 'company_id', $records[PurgeTarget::Companies->value], $owner)];
        $records[PurgeTarget::Projects->value] = [...$records[PurgeTarget::Projects->value], ...$this->children('project', 'product_id', $records[PurgeTarget::Products->value], $owner)];
        $records[PurgeTarget::JournalEntries->value] = [...$records[PurgeTarget::JournalEntries->value], ...$this->children('journal_entry', 'project_id', $records[PurgeTarget::Projects->value], $owner)];
        $records[PurgeTarget::Tags->value] = [...$records[PurgeTarget::Tags->value], ...$this->children('tag', 'category_id', $records[PurgeTarget::Categories->value], $owner)];

        $tags = $records[PurgeTarget::Tags->value];
        $untagProjects = array_values(array_diff($this->tagged('project_tag', 'project_id', $tags), $records[PurgeTarget::Projects->value]));
        $untagEntries = array_values(array_diff($this->tagged('journal_entry_tag', 'journal_entry_id', $tags), $records[PurgeTarget::JournalEntries->value]));

        return new PurgePlan(
            $records,
            $untagProjects,
            $untagEntries,
            $this->children('journal_media', 'journal_entry_id', $records[PurgeTarget::JournalEntries->value], $owner),
        );
    }

    /**
     * @param list<string> $parentIds
     *
     * @return list<string>
     *
     * @throws Exception
     */
    private function children(string $table, string $column, array $parentIds, string $owner): array
    {
        return $this->ids("SELECT id FROM $table WHERE user_id = :owner AND $column IN (:ids) ORDER BY id", $parentIds, ['owner' => $owner]);
    }

    /**
     * What carries any of these tags (a tag's links are its owner's own).
     *
     * @param list<string> $tagIds
     *
     * @return list<string>
     *
     * @throws Exception
     */
    private function tagged(string $table, string $column, array $tagIds): array
    {
        return $this->ids("SELECT DISTINCT $column FROM $table WHERE tag_id IN (:ids) ORDER BY $column", $tagIds);
    }

    /**
     * @param list<string>          $ids
     * @param array<string, string> $params
     *
     * @return list<string>
     *
     * @throws Exception
     */
    private function ids(string $sql, array $ids, array $params = []): array
    {
        if ([] === $ids) {
            return [];
        }
        /** @var list<string> $found */
        $found = $this->db->fetchFirstColumn($sql, ['ids' => $ids] + $params, ['ids' => ArrayParameterType::STRING]);

        return $found;
    }
}
