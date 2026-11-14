<?php

declare(strict_types=1);

namespace App\Tests\Functional\Migrations;

use App\Company\CompanyEntity;
use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineMigrations\Version20261115100000;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * The migrations that move a module to event sourcing turn its existing rows
 * into events. They must replay to exactly the rows they came from: seed
 * rows as the old code stored them, import, rebuild the projections from the
 * events alone, and compare.
 */
final class ImportMigrationsTest extends KernelTestCase
{
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
        $owner = new User('me@example.com');
        $company = new CompanyEntity($owner, 'Acme');
        $project = $company->addProduct('Widget')->addProject('Launch');
        $category = new CategoryEntity($owner, 'Skill');
        [$php, $sql] = [$category->addTag('php'), $category->addTag('sql')];
        foreach ([$owner, $company, $category] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        // Rows as the ORM-backed code stored them
        [$kickoff, $archived] = [Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122()];
        foreach ([
            [$kickoff, 'Kickoff', "Agreed on the scope.\nNext: estimates.", '2024-01-02 03:04:05', null],
            [$archived, 'Old', 'Ünïcode & "quotes"', '2024-02-03 04:05:06', '2024-03-04 05:06:07'],
        ] as [$id, $title, $entry, $created, $archivedAt]) {
            $this->db->insert('journal_entry', ['id' => $id, 'project_id' => $project->id->toRfc4122(), 'user_id' => $owner->id->toRfc4122(), 'title' => $title, 'entry' => $entry, 'created_at' => $created, 'archived_at' => $archivedAt]);
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
    private function migrate(AbstractMigration $migration): void
    {
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $this->db->executeStatement($query->getStatement());  // the imports take no parameters
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
