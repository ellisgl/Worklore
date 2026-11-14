<?php

declare(strict_types=1);

namespace App\Purge;

use function count;
use function in_array;

/**
 * Everything one purge erases: the record asked for and everything under it,
 * plus the records that only lose a tag that is going away.
 */
final readonly class PurgePlan
{
    /**
     * @param array<string, list<string>> $records       ids by PurgeTarget value, every target present
     * @param list<string>                $untagProjects projects (not purged) that carry a purged tag
     * @param list<string>                $untagEntries  journal entries (not purged) that carry a purged tag
     * @param list<string>                $mediaIds      files attached to the purged journal entries
     */
    public function __construct(
        public array $records,
        public array $untagProjects,
        public array $untagEntries,
        public array $mediaIds,
    ) {
    }

    /**
     * @return list<string>
     */
    public function ids(PurgeTarget $target): array
    {
        return $this->records[$target->value] ?? [];
    }

    /**
     * @return list<string> every purged record, of every type
     */
    public function allIds(): array
    {
        $ids = [];
        foreach ($this->records as $records) {
            foreach ($records as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function purgesTag(string $tagId): bool
    {
        return in_array($tagId, $this->ids(PurgeTarget::Tags), true);
    }

    public function report(bool $dryRun): PurgeReport
    {
        $purged = [];
        foreach (PurgeTarget::cases() as $target) {
            $purged[$target->key()] = count($this->ids($target));
        }

        return new PurgeReport(
            dryRun: $dryRun,
            purged: $purged,
            untagged: ['projects' => count($this->untagProjects), 'journal_entries' => count($this->untagEntries)],
            files: count($this->mediaIds),
        );
    }
}
