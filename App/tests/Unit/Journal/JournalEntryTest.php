<?php

declare(strict_types=1);

namespace App\Tests\Unit\Journal;

use App\Company\ProjectId;
use App\Journal\Event\JournalEntryArchived;
use App\Journal\Event\JournalEntryRetagged;
use App\Journal\Event\JournalEntryRetitled;
use App\Journal\Event\JournalEntryRewritten;
use App\Journal\Event\JournalEntryUnarchived;
use App\Journal\Event\JournalEntryWritten;
use App\Journal\JournalEntry;
use App\Journal\JournalEntryId;
use App\Shared\InvalidValueException;
use App\Shared\UserId;
use App\Taxonomy\TagId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function array_map;
use function sort;

/**
 * Given its history, when the entry is told to change, then it records
 * these events (or refuses).
 */
final class JournalEntryTest extends TestCase
{
    public function testWritingRecordsTheTrimmedEntryWithDistinctTags(): void
    {
        [$id, $project, $owner, $php, $sql] = [JournalEntryId::generate(), ProjectId::generate(), UserId::generate(), TagId::generate(), TagId::generate()];
        $at = new DateTimeImmutable('2026-03-04 05:06:07 UTC');

        $entry = JournalEntry::write($id, $project, $owner, '  Kickoff  ', '  Agreed on the scope.  ', [$sql, $php, $sql], $at);

        [$written] = $entry->releaseEvents();
        self::assertInstanceOf(JournalEntryWritten::class, $written);
        self::assertSame('Kickoff', $written->title);
        self::assertSame('Agreed on the scope.', $written->entry);
        self::assertSame(self::sorted([$php, $sql]), self::ids($written->tagIds), 'Distinct, sorted by id');
        self::assertSame($at, $written->writtenAt);
        self::assertTrue($owner->equals($entry->ownerId()));
    }

    public function testBlankTitleOrTextIsRefused(): void
    {
        foreach ([['  ', 'Body', 'Journal entry title cannot be blank'], ['Kickoff', " \n ", 'Journal entry cannot be blank']] as [$title, $text, $message]) {
            try {
                self::entry(title: $title, text: $text);
                self::fail('Expected InvalidValueException');
            } catch (InvalidValueException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
    }

    public function testChangesRecordWhatChanged(): void
    {
        $entry = self::saved(self::entry());
        $tag = TagId::generate();

        $entry->retitle(' Kick-off ');
        $entry->rewrite('Scope moved.');
        $entry->retag([$tag]);

        $events = $entry->releaseEvents();
        self::assertSame([JournalEntryRetitled::class, JournalEntryRewritten::class, JournalEntryRetagged::class], self::classes($events));
        self::assertSame('Kick-off', $entry->title->value);
        self::assertSame('Scope moved.', $entry->entry);
        self::assertSame([$tag->toRfc4122()], self::ids($entry->tagIds));
    }

    public function testNoOpChangesRecordNothing(): void
    {
        $tag = TagId::generate();
        $entry = self::saved(self::entry(tags: [$tag]));

        $entry->retitle('Kickoff');
        $entry->rewrite(' Body ');
        $entry->retag([$tag, $tag]);
        $entry->unarchive();

        self::assertSame([], $entry->releaseEvents());
    }

    public function testArchivingKeepsTheFirstTime(): void
    {
        $entry = self::saved(self::entry());
        $first = new DateTimeImmutable('2026-01-01 00:00:00 UTC');

        $entry->archive($first);
        $entry->archive(new DateTimeImmutable('2026-02-01 00:00:00 UTC'));
        $entry->unarchive();

        self::assertSame([JournalEntryArchived::class, JournalEntryUnarchived::class], self::classes($entry->releaseEvents()));
        self::assertNull($entry->archivedAt);
    }

    public function testReplayingTheHistoryRebuildsTheSameEntry(): void
    {
        $entry = self::entry();
        $entry->retitle('Kick-off');
        $entry->archive(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));
        $history = $entry->releaseEvents();

        $replayed = JournalEntry::fromHistory($history);

        self::assertSame('Kick-off', $replayed->title->value);
        self::assertNotNull($replayed->archivedAt);
        self::assertSame(3, $replayed->version());
        self::assertSame([], $replayed->releaseEvents());
    }

    /**
     * @param list<TagId> $tags
     */
    private static function entry(string $title = 'Kickoff', string $text = 'Body', array $tags = []): JournalEntry
    {
        return JournalEntry::write(JournalEntryId::generate(), ProjectId::generate(), UserId::generate(), $title, $text, $tags, new DateTimeImmutable());
    }

    /**
     * As if loaded from the store: its creation is history, not news.
     */
    private static function saved(JournalEntry $entry): JournalEntry
    {
        return JournalEntry::fromHistory($entry->releaseEvents());
    }

    /**
     * @param list<object> $events
     *
     * @return list<class-string>
     */
    private static function classes(array $events): array
    {
        return array_map(static fn (object $e) => $e::class, $events);
    }

    /**
     * @param list<TagId> $ids
     *
     * @return list<string>
     */
    private static function ids(array $ids): array
    {
        return array_map(static fn (TagId $id) => $id->toRfc4122(), $ids);
    }

    /**
     * @param list<TagId> $ids
     *
     * @return list<string>
     */
    private static function sorted(array $ids): array
    {
        $strings = self::ids($ids);
        sort($strings);

        return $strings;
    }
}
