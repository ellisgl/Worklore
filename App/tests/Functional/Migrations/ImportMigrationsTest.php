<?php

declare(strict_types=1);

namespace App\Tests\Functional\Migrations;

use App\Taxonomy\CategoryEntity;
use App\Tests\Functional\CreatesCompanyRecords;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineMigrations\Version20261115100000;
use DoctrineMigrations\Version20261115110000;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

use function ltrim;
use function str_starts_with;

/**
 * The migrations that move a module to event sourcing turn its existing rows
 * into events. They must replay to exactly the rows they came from: seed
 * rows as the old code stored them, import, rebuild the projections from the
 * events alone, and compare.
 */
final class ImportMigrationsTest extends KernelTestCase
{
    use CreatesCompanyRecords;

    private Connection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = self::getContainer()->get(Connection::class);
    }

    /**
     * @throws Exception
     */
    public function testJournalEntriesReplayToTheSameRows(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->owner();
        $project = $this->project($this->product($this->company($owner)));
        $category = new CategoryEntity($owner, 'Skill');
        [$php, $sql] = [$category->addTag('php'), $category->addTag('sql')];
        $em->persist($category);
        $em->flush();

        // Rows as the ORM-backed code stored them
        [$kickoff, $archived] = [Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122()];
        foreach ([
            [$kickoff, 'Kickoff', "Agreed on the scope.\nNext: estimates.", '2024-01-02 03:04:05', null],
            [$archived, 'Old', 'Ünïcode & "quotes"', '2024-02-03 04:05:06', '2024-03-04 05:06:07'],
        ] as [$id, $title, $entry, $created, $archivedAt]) {
            $this->db->insert('journal_entry', ['id' => $id, 'project_id' => $project->id()->toRfc4122(), 'user_id' => $owner->id->toRfc4122(), 'title' => $title, 'entry' => $entry, 'created_at' => $created, 'archived_at' => $archivedAt]);
        }
        foreach ([$sql, $php] as $tag) {
            $this->db->insert('journal_entry_tag', ['journal_entry_id' => $kickoff, 'tag_id' => $tag->id->toRfc4122()]);
        }
        $before = $this->journalRows();

        $this->migrate(new Version20261115100000($this->db, new NullLogger()));
        $this->rebuild();

        self::assertSame($before, $this->journalRows());
        self::assertEquals(3, $this->db->fetchOne("SELECT count(*) FROM event_store WHERE stream_type = 'journal_entry'"), 'Written ×2, archived ×1');
    }

    /**
     * @throws Exception
     */
    public function testCompanyRecordsReplayToTheSameRows(): void
    {
        $owner = $this->owner();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $category = new CategoryEntity($owner, 'Skill');
        [$php, $sql] = [$category->addTag('php'), $category->addTag('sql')];
        $em->persist($category);
        $em->flush();
        $user = $owner->id->toRfc4122();

        // Rows as the ORM-backed code stored them
        [$acme, $gone, $widget, $retired, $launch, $old] = [self::id(), self::id(), self::id(), self::id(), self::id(), self::id()];
        $this->db->insert('company', ['id' => $acme, 'user_id' => $user, 'name' => 'Acme', 'name_key' => 'acme', 'overview' => "Ünïcode & \"quotes\"\nline two", 'created_at' => '2024-01-02 03:04:05', 'archived_at' => null]);
        $this->db->insert('company', ['id' => $gone, 'user_id' => $user, 'name' => 'Gone', 'name_key' => 'gone', 'overview' => null, 'created_at' => '2024-01-03 00:00:00', 'archived_at' => '2024-05-06 07:08:09']);
        $this->db->insert('product', ['id' => $widget, 'company_id' => $acme, 'user_id' => $user, 'name' => 'Widget', 'name_key' => 'widget', 'overview' => 'Ours', 'created_at' => '2024-02-01 00:00:00', 'archived_at' => null]);
        $this->db->insert('product', ['id' => $retired, 'company_id' => $acme, 'user_id' => $user, 'name' => 'Retired', 'name_key' => 'retired', 'overview' => null, 'created_at' => '2024-02-02 00:00:00', 'archived_at' => '2024-03-01 00:00:00']);
        $this->db->insert('project', ['id' => $launch, 'product_id' => $widget, 'user_id' => $user, 'name' => 'Launch', 'name_key' => 'launch', 'overview' => null, 'created_at' => '2024-02-03 00:00:00', 'archived_at' => null]);
        $this->db->insert('project', ['id' => $old, 'product_id' => $widget, 'user_id' => $user, 'name' => 'Old', 'name_key' => 'old', 'overview' => 'Done', 'created_at' => '2024-02-04 00:00:00', 'archived_at' => '2024-04-01 00:00:00']);
        foreach ([$sql, $php] as $tag) {
            $this->db->insert('project_tag', ['project_id' => $launch, 'tag_id' => $tag->id->toRfc4122()]);
        }
        foreach ([
            ['Current', '2022-01-01', null, null, null],
            ['Ended', '2019-01-01', '2021-12-31', 'Moved on', null],
            ['Ended, archived', '2015-01-01', '2016-12-31', null, '2024-06-01 00:00:00'],
            ['Archived', '2017-01-01', null, null, '2024-06-02 00:00:00'],
        ] as [$title, $start, $end, $reason, $archivedAt]) {
            $this->db->insert('"position"', ['id' => self::id(), 'company_id' => $acme, 'user_id' => $user, 'title' => $title, 'overview' => null, 'start_date' => $start, 'end_date' => $end, 'reason_left' => $reason, 'created_at' => '2024-01-05 00:00:00', 'archived_at' => $archivedAt]);
        }
        $before = $this->companyRows();

        $this->migrate(new Version20261115110000($this->db, new NullLogger()));
        $this->rebuild();

        self::assertSame($before, $this->companyRows());
        $counts = [];
        foreach (['company', 'product', 'project', 'position'] as $stream) {
            $counts[$stream] = $this->db->fetchOne('SELECT count(*) FROM event_store WHERE stream_type = ?', [$stream]);
        }
        self::assertEquals(['company' => 3, 'product' => 3, 'project' => 3, 'position' => 8], $counts, 'Created per record, plus archived (and ended, for positions)');
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     *
     * @throws Exception
     */
    private function companyRows(): array
    {
        $rows = [];
        foreach (['company', 'product', 'project', '"position"'] as $table) {
            $rows[$table] = $this->db->fetchAllAssociative("SELECT * FROM $table ORDER BY id");
        }
        $rows['project_tag'] = $this->db->fetchAllAssociative('SELECT * FROM project_tag ORDER BY project_id, tag_id');

        return $rows;
    }

    private static function id(): string
    {
        return Uuid::v7()->toRfc4122();
    }

    /**
     * Runs a migration's imports: its INSERT INTO event_store statements.
     * Its schema changes (dropped keys and tables) are already applied in
     * the test database.
     *
     * @throws Exception
     */
    private function migrate(AbstractMigration $migration): void
    {
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            if (str_starts_with(ltrim($query->getStatement()), 'INSERT INTO event_store')) {
                $this->db->executeStatement($query->getStatement());  // the imports take no parameters
            }
        }
    }

    private function rebuild(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $tester = new CommandTester(new Application($kernel)->find('app:projections:rebuild'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
    }

    /**
     * @return array{entries: list<array<string, mixed>>, tags: list<array<string, mixed>>}
     *
     * @throws Exception
     */
    private function journalRows(): array
    {
        return [
            'entries' => $this->db->fetchAllAssociative('SELECT * FROM journal_entry ORDER BY id'),
            'tags' => $this->db->fetchAllAssociative('SELECT * FROM journal_entry_tag ORDER BY journal_entry_id, tag_id'),
        ];
    }
}
