<?php

declare(strict_types=1);

namespace App\Tests\Functional\Journal;

use App\Company\Project;
use App\Identity\User;
use App\Journal\JournalEntry;
use App\Journal\JournalEntryId;
use App\Journal\JournalEntryRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Tests\Functional\CreatesRecords;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

final class JournalEntryRepositoryTest extends KernelTestCase
{
    use CreatesRecords;

    private JournalEntryRepository $entries;
    private User $owner;
    private Project $launch;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->owner = $this->owner();
        $this->launch = $this->project($this->product($this->company($this->owner)));
        $this->entries = self::getContainer()->get(JournalEntryRepository::class);
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testSavesAndLoadsAnEntryFromItsEvents(): void
    {
        $entry = $this->write();
        $entry->retitle('Kick-off');
        $this->entries->save($entry);

        $loaded = $this->entries->findForOwner($entry->id(), $this->owner->id);

        self::assertNotNull($loaded);
        self::assertSame('Kick-off', $loaded->title->value);
        self::assertSame(2, $loaded->version());
        self::assertSame(
            ['journal_entry.written', 'journal_entry.retitled'],
            self::getContainer()->get(Connection::class)->fetchFirstColumn("SELECT event_type FROM event_store WHERE stream_type = 'journal_entry' ORDER BY sequence"),
        );
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testSavingProjectsIntoTheReadTable(): void
    {
        $entry = $this->write();
        $entry->archive(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));
        $this->entries->save($entry);

        $row = self::getContainer()->get(Connection::class)->fetchAssociative('SELECT title, archived_at FROM journal_entry WHERE id = ?', [$entry->id()->toRfc4122()]);

        self::assertSame(['title' => 'Kickoff', 'archived_at' => '2026-01-01 00:00:00'], $row);
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testAnotherOwnerOrAnUnknownIdFindsNothing(): void
    {
        $entry = $this->write();
        $other = $this->owner('other@example.com');

        self::assertNull($this->entries->findForOwner($entry->id(), $other->id));
        self::assertNull($this->entries->findForOwner(JournalEntryId::generate(), $this->owner->id));
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testAStaleCopyCannotBeSaved(): void
    {
        $id = $this->write()->id();
        $first = $this->entries->findForOwner($id, $this->owner->id);
        $second = $this->entries->findForOwner($id, $this->owner->id);
        self::assertNotNull($first);
        self::assertNotNull($second);

        $first->retitle('First');
        $this->entries->save($first);
        $second->retitle('Second');

        $this->expectException(ConcurrentModification::class);
        $this->entries->save($second);
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    private function write(): JournalEntry
    {
        $entry = JournalEntry::write(JournalEntryId::generate(), $this->launch->id(), $this->owner->id, 'Kickoff', 'Agreed on the scope.', [], new DateTimeImmutable());
        $this->entries->save($entry);

        return $entry;
    }
}
