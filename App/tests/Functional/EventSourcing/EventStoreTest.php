<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSourcing;

use App\Identity\User;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\EventSourcing\EventSerializer;
use App\Shared\EventSourcing\EventStore;
use App\Tests\Fixtures\EventSourcing\Note;
use App\Tests\Fixtures\EventSourcing\NoteEdited;
use App\Tests\Fixtures\EventSourcing\NoteEvents;
use App\Tests\Fixtures\EventSourcing\NoteId;
use DateMalformedStringException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

use function array_map;
use function iterator_to_array;

final class EventStoreTest extends KernelTestCase
{
    private EventStore $store;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->owner = new User('me@example.com');
        $em->persist($this->owner);
        $em->flush();
        $this->store = new EventStore(self::getContainer()->get(Connection::class), new EventSerializer([new NoteEvents()]), new MockClock('2026-03-04 05:06:07 UTC'));
    }

    /**
     * @throws ConcurrentModification
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws JsonException
     */
    public function testAppendsAndReadsAStream(): void
    {
        $note = Note::write(NoteId::generate(), $this->owner->id, 'Draft');
        $note->edit('Final');

        $stored = $this->store->append('note', $note->id(), $note->ownerId(), $note->version(), $note->releaseEvents());

        self::assertSame([1, 2], array_map(static fn ($e) => $e->version, $stored));
        $stream = $this->store->stream('note', $note->id());
        self::assertSame(['note.written', 'note.edited'], array_map(static fn ($e) => $e->type, $stream));
        self::assertSame('2026-03-04 05:06:07', $stream[0]->recordedAt->format('Y-m-d H:i:s'));
        self::assertTrue($this->owner->id->equals($stream[1]->ownerId));
        self::assertSame('Final', Note::fromHistory(array_map(static fn ($e) => $e->event, $stream))->text, 'Replays to the same state');
        self::assertSame([], $this->store->stream('note', NoteId::generate()));
        self::assertSame([], $this->store->stream('other', $note->id()), 'Scoped to the stream type');
    }

    /**
     * @throws ConcurrentModification
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws JsonException
     */
    public function testRefusesToAppendOnAStaleVersion(): void
    {
        $id = NoteId::generate();
        $note = Note::write($id, $this->owner->id, 'Draft');
        $this->store->append('note', $id, $this->owner->id, 0, $note->releaseEvents());

        // Two requests load version 1; the first saves, the second must not.
        $this->store->append('note', $id, $this->owner->id, 1, [new NoteEdited($id, 'First')]);

        $this->expectException(ConcurrentModification::class);
        $this->store->append('note', $id, $this->owner->id, 1, [new NoteEdited($id, 'Second')]);
    }

    /**
     * @throws ConcurrentModification
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws JsonException
     */
    public function testReadsEverythingInOrderInBatches(): void
    {
        $a = Note::write(NoteId::generate(), $this->owner->id, 'A');
        $b = Note::write(NoteId::generate(), $this->owner->id, 'B');
        $this->store->append('note', $a->id(), $this->owner->id, 0, $a->releaseEvents());
        $this->store->append('note', $b->id(), $this->owner->id, 0, $b->releaseEvents());
        $this->store->append('note', $a->id(), $this->owner->id, 1, [new NoteEdited($a->id(), 'A2')]);

        $all = iterator_to_array($this->store->all(batchSize: 2), false);

        self::assertSame(['note.written', 'note.written', 'note.edited'], array_map(static fn ($e) => $e->type, $all));
        self::assertGreaterThan($all[0]->sequence, $all[1]->sequence);
        self::assertGreaterThan($all[1]->sequence, $all[2]->sequence);
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws JsonException
     */
    public function testDeletingTheOwnerDeletesTheirEvents(): void
    {
        $note = Note::write(NoteId::generate(), $this->owner->id, 'Draft');
        $this->store->append('note', $note->id(), $this->owner->id, 0, $note->releaseEvents());
        $db = self::getContainer()->get(Connection::class);

        $db->executeStatement('DELETE FROM app_user WHERE id = ?', [$this->owner->id->toRfc4122()]);

        self::assertEquals(0, $db->fetchOne('SELECT count(*) FROM event_store'));
    }
}
