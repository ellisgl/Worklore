<?php

declare(strict_types=1);

namespace App\Tests\Functional\Journal;

use App\Company\CompanyEntity;
use App\Company\ProjectEntity;
use App\Identity\User;
use App\Journal\JournalEntry;
use App\Journal\JournalEntryId;
use App\Journal\JournalEntryRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

final class JournalEntryRepositoryTest extends KernelTestCase
{
    private JournalEntryRepository $entries;
    private User $owner;
    private ProjectEntity $project;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->owner = new User('me@example.com');
        $company = new CompanyEntity($this->owner, 'Acme');
        $this->project = $company->addProduct('Widget')->addProject('Launch');
        $em->persist($this->owner);
        $em->persist($company);
        $em->flush();
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
            self::getContainer()->get(Connection::class)->fetchFirstColumn('SELECT event_type FROM event_store ORDER BY sequence'),
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
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $other = new User('other@example.com');
        $em->persist($other);
        $em->flush();

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
        $entry = JournalEntry::write(JournalEntryId::generate(), $this->project->id, $this->owner->id, 'Kickoff', 'Agreed on the scope.', [], new DateTimeImmutable());
        $this->entries->save($entry);

        return $entry;
    }
}
