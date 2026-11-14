<?php

declare(strict_types=1);

namespace App\Tests\Unit\Journal;

use App\Company\ProjectId;
use App\Journal\Event\JournalEntryMediaAttached;
use App\Journal\Event\JournalEntryMediaRemoved;
use App\Journal\Event\JournalEntryWritten;
use App\Journal\JournalEntry;
use App\Journal\JournalEntryId;
use App\Journal\Media;
use App\Journal\MediaId;
use App\Journal\MediaNotFound;
use App\Shared\InvalidValueException;
use App\Shared\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function hash;
use function mb_strlen;
use function str_repeat;

final class JournalEntryMediaTest extends TestCase
{
    public function testAttachingRecordsTheFileOnce(): void
    {
        $entry = self::saved();
        $media = self::media('photo.png');
        $at = new DateTimeImmutable('2026-03-04 05:06:07 UTC');

        $entry->attach($media, $at);
        $entry->attach($media, $at);

        $events = $entry->releaseEvents();
        self::assertEquals([new JournalEntryMediaAttached($entry->id(), $media->id, 'photo.png', 'image/png', 70, hash('sha256', 'x'), $at)], $events);
        self::assertEquals([$media], JournalEntry::fromHistory([...self::history($entry), ...$events])->media(), 'Replayed');
    }

    public function testRemovingForgetsTheFile(): void
    {
        $entry = self::saved();
        $kept = self::media('kept.png');
        $gone = self::media('gone.png');
        $entry->attach($kept, new DateTimeImmutable());
        $entry->attach($gone, new DateTimeImmutable());
        $at = new DateTimeImmutable('2026-03-04 05:06:07 UTC');

        self::assertEquals($gone, $entry->removeMedia($gone->id, $at));

        self::assertEquals(new JournalEntryMediaRemoved($entry->id(), $gone->id, $at), $entry->releaseEvents()[2]);
        self::assertEquals([$kept], $entry->media());
        $this->expectException(MediaNotFound::class);
        $entry->removeMedia($gone->id, $at);
    }

    public function testFilenamesAreTidied(): void
    {
        self::assertSame('passwd', Media::filename('../../etc/passwd'));
        self::assertSame('photo.png', Media::filename('C:\\Users\\me\\photo.png'));
        self::assertSame('evil.png', Media::filename("evil\u{202E}\0.png"));
        self::assertSame('file', Media::filename(' .. '));
        $long = Media::filename(str_repeat('a', 300).'.jpeg');
        self::assertSame(255, mb_strlen($long));
        self::assertStringEndsWith('a.jpeg', $long, 'Keeps the extension');
    }

    public function testRejectsImpossibleFiles(): void
    {
        $this->expectException(InvalidValueException::class);
        new Media(MediaId::generate(), 'empty.png', 'image/png', 0, hash('sha256', ''));
    }

    private static function media(string $filename): Media
    {
        return new Media(MediaId::generate(), $filename, 'image/png', 70, hash('sha256', 'x'));
    }

    private static function saved(): JournalEntry
    {
        $entry = JournalEntry::write(JournalEntryId::generate(), ProjectId::generate(), UserId::generate(), 'Kickoff', 'Body', [], new DateTimeImmutable());

        return JournalEntry::fromHistory($entry->releaseEvents());
    }

    /**
     * @return list<JournalEntryWritten>
     */
    private static function history(JournalEntry $entry): array
    {
        return [new JournalEntryWritten($entry->id(), $entry->projectId, $entry->ownerId(), $entry->title->value, $entry->entry, [], new DateTimeImmutable())];
    }
}
