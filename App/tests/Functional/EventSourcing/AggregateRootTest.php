<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSourcing;

use App\Shared\UserId;
use App\Tests\Fixtures\EventSourcing\Note;
use App\Tests\Fixtures\EventSourcing\NoteEdited;
use App\Tests\Fixtures\EventSourcing\NoteId;
use App\Tests\Fixtures\EventSourcing\NoteWritten;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AggregateRootTest extends TestCase
{
    public function testRecordsWhatHappensAndCountsSavedEvents(): void
    {
        $note = Note::write(NoteId::generate(), UserId::generate(), 'Draft');
        $note->edit('Draft');   // no change: nothing recorded
        $note->edit('Final');

        self::assertSame(0, $note->version(), 'Nothing saved yet');
        $events = $note->releaseEvents();
        self::assertSame([NoteWritten::class, NoteEdited::class], [$events[0]::class, $events[1]::class]);
        self::assertSame(2, $note->version());
        self::assertSame([], $note->releaseEvents(), 'Released once');
    }

    public function testReplayRebuildsStateAndVersion(): void
    {
        $id = NoteId::generate();
        $note = Note::fromHistory([
            new NoteWritten($id, UserId::generate(), 'Draft', null, new DateTimeImmutable()),
            new NoteEdited($id, 'Final'),
        ]);

        self::assertSame('Final', $note->text);
        self::assertSame(2, $note->version());
        self::assertSame([], $note->releaseEvents(), 'Replayed events are not new');
    }
}
