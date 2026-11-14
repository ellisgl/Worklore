<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSourcing;

use App\Shared\EventSourcing\EventCatalog;
use App\Shared\EventSourcing\EventSerializer;
use App\Shared\UserId;
use App\Tests\Fixtures\EventSourcing\NoteEdited;
use App\Tests\Fixtures\EventSourcing\NoteEvents;
use App\Tests\Fixtures\EventSourcing\NoteId;
use App\Tests\Fixtures\EventSourcing\NoteWritten;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class EventSerializerTest extends TestCase
{
    public function testRoundTripsAnEvent(): void
    {
        $serializer = new EventSerializer([new NoteEvents()]);
        [$note, $linked, $owner] = [NoteId::generate(), NoteId::generate(), UserId::generate()];
        $event = new NoteWritten($note, $owner, 'Hello', null, new DateTimeImmutable('2026-03-04 05:06:07', new DateTimeZone('Europe/Paris')), [$linked], 2);

        $payload = $serializer->payload($event);

        self::assertSame('note.written', $serializer->typeOf($event));
        self::assertSame([
            'note_id' => $note->toRfc4122(),
            'owner_id' => $owner->toRfc4122(),
            'text' => 'Hello',
            'mood' => null,
            'written_at' => '2026-03-04T04:06:07+00:00',
            'linked_note_ids' => [$linked->toRfc4122()],
            'priority' => 2,
        ], $payload, 'snake_case keys; ids as strings; times in UTC');

        $back = $serializer->event('note.written', $payload);
        self::assertInstanceOf(NoteWritten::class, $back);
        self::assertTrue($note->equals($back->noteId));
        self::assertTrue($linked->equals($back->linkedNoteIds[0]));
        self::assertSame('2026-03-04T04:06:07+00:00', $back->writtenAt->format(DATE_ATOM));
        self::assertSame(2, $back->priority);
    }

    public function testMissingKeysTakeTheParametersDefaults(): void
    {
        $serializer = new EventSerializer([new NoteEvents()]);
        $old = ['note_id' => NoteId::generate()->toRfc4122(), 'owner_id' => UserId::generate()->toRfc4122(), 'text' => 'Old', 'mood' => 'calm', 'written_at' => '2020-01-01T00:00:00+00:00'];

        $event = $serializer->event('note.written', $old);

        self::assertInstanceOf(NoteWritten::class, $event);
        self::assertSame([], $event->linkedNoteIds);
        self::assertSame(0, $event->priority);
    }

    public function testRejectsUnknownTypesAndBadPayloads(): void
    {
        $serializer = new EventSerializer([new NoteEvents()]);

        foreach ([
            ['note.deleted', []],
            ['note.edited', ['note_id' => NoteId::generate()->toRfc4122()]],
            ['note.edited', ['note_id' => NoteId::generate()->toRfc4122(), 'text' => 42]],
            ['note.edited', ['note_id' => null, 'text' => 'x']],
        ] as [$type, $payload]) {
            try {
                $serializer->event($type, $payload);
                self::fail("Expected $type to be rejected");
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testATypeNameCanOnlyBeRegisteredOnce(): void
    {
        $this->expectException(LogicException::class);

        new EventSerializer([new NoteEvents(), new class implements EventCatalog {
            public function events(): array
            {
                return ['note.edited' => NoteEdited::class];
            }
        }]);
    }
}
