<?php

declare(strict_types=1);

namespace App\Tests\Functional\Journal;

use App\Journal\Query\JournalEntryQueries;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function implode;
use function is_string;

/**
 * JournalEntryQueries::SEARCH must be able to use idx_journal_entry_search:
 * its expression has to match the index's exactly.
 */
final class JournalSearchIndexTest extends KernelTestCase
{
    /**
     * @throws Exception
     */
    public function testSearchCanUseTheIndex(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);

        // The search condition alone (with the owner's too, the planner may
        // prefer the user_id index), and no sequential scans: with a near-empty
        // table the planner would scan anyway. This asks whether the index is
        // usable, not whether it's the cheapest.
        $db->executeStatement('SET LOCAL enable_seqscan = off');
        $plan = $db->fetchFirstColumn(
            'EXPLAIN SELECT j.id FROM journal_entry j WHERE '.JournalEntryQueries::SEARCH,
            ['search' => 'deploy'],
        );
        $lines = [];
        foreach ($plan as $line) {
            $lines[] = is_string($line) ? $line : '';
        }

        self::assertStringContainsString('idx_journal_entry_search', implode("\n", $lines));
    }
}
