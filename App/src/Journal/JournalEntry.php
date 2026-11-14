<?php

declare(strict_types=1);

namespace App\Journal;

use App\Company\ProjectId;
use App\Journal\Event\JournalEntryArchived;
use App\Journal\Event\JournalEntryRetagged;
use App\Journal\Event\JournalEntryRetitled;
use App\Journal\Event\JournalEntryRewritten;
use App\Journal\Event\JournalEntryUnarchived;
use App\Journal\Event\JournalEntryWritten;
use App\Shared\EventSourcing\AggregateRoot;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\InvalidValueException;
use App\Shared\Name;
use App\Shared\UserId;
use App\Taxonomy\TagId;
use DateTimeImmutable;
use LogicException;

use function array_values;
use function trim;
use function usort;

/**
 * A note written for a project, labelled with tags; an event-sourced
 * aggregate (stream "journal_entry"). The project and tags must be the
 * owner's; the command handlers look them up before calling in.
 */
final class JournalEntry extends AggregateRoot
{
    private JournalEntryId $id;
    private UserId $ownerId;
    private(set) ProjectId $projectId;
    private(set) Name $title;
    private(set) string $entry;
    /** @var list<TagId> sorted by id */
    private(set) array $tagIds = [];
    private(set) ?DateTimeImmutable $archivedAt = null;

    private function __construct()
    {
    }

    /**
     * @param list<TagId> $tagIds duplicates are dropped
     *
     * @throws InvalidValueException if the title or text is blank, or the title is too long
     */
    public static function write(
        JournalEntryId $id,
        ProjectId $projectId,
        UserId $ownerId,
        string $title,
        string $entry,
        array $tagIds,
        DateTimeImmutable $at,
    ): self {
        $journalEntry = new self();
        $journalEntry->recordThat(new JournalEntryWritten(
            $id,
            $projectId,
            $ownerId,
            Name::of($title, 'Journal entry title')->value,
            self::text($entry),
            self::distinct($tagIds),
            $at,
        ));

        return $journalEntry;
    }

    /**
     * @param iterable<DomainEvent> $history
     */
    public static function fromHistory(iterable $history): self
    {
        $journalEntry = new self();
        $journalEntry->replay($history);

        return $journalEntry;
    }

    public function id(): JournalEntryId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    /**
     * @throws InvalidValueException if the title is blank or too long
     */
    public function retitle(string $title): void
    {
        $title = Name::of($title, 'Journal entry title');
        if (!$title->equals($this->title)) {
            $this->recordThat(new JournalEntryRetitled($this->id, $title->value));
        }
    }

    /**
     * @throws InvalidValueException if the text is blank
     */
    public function rewrite(string $entry): void
    {
        $entry = self::text($entry);
        if ($entry !== $this->entry) {
            $this->recordThat(new JournalEntryRewritten($this->id, $entry));
        }
    }

    /**
     * Replaces the tags with exactly these (duplicates dropped).
     *
     * @param list<TagId> $tagIds
     */
    public function retag(array $tagIds): void
    {
        $tagIds = self::distinct($tagIds);
        if ($tagIds != $this->tagIds) {
            $this->recordThat(new JournalEntryRetagged($this->id, $tagIds));
        }
    }

    /**
     * Archiving an archived entry keeps its original archive time.
     */
    public function archive(DateTimeImmutable $at): void
    {
        if (null === $this->archivedAt) {
            $this->recordThat(new JournalEntryArchived($this->id, $at));
        }
    }

    public function unarchive(): void
    {
        if (null !== $this->archivedAt) {
            $this->recordThat(new JournalEntryUnarchived($this->id));
        }
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof JournalEntryWritten => $this->written($event),
            $event instanceof JournalEntryRetitled => $this->title = Name::of($event->title, 'Journal entry title'),
            $event instanceof JournalEntryRewritten => $this->entry = $event->entry,
            $event instanceof JournalEntryRetagged => $this->tagIds = $event->tagIds,
            $event instanceof JournalEntryArchived => $this->archivedAt = $event->archivedAt,
            $event instanceof JournalEntryUnarchived => $this->archivedAt = null,
            default => throw new LogicException('Not a journal entry event: '.$event::class),
        };
    }

    private function written(JournalEntryWritten $event): void
    {
        $this->id = $event->entryId;
        $this->projectId = $event->projectId;
        $this->ownerId = $event->ownerId;
        $this->title = Name::of($event->title, 'Journal entry title');
        $this->entry = $event->entry;
        $this->tagIds = $event->tagIds;
    }

    /**
     * Entries are required: blank or whitespace-only text is rejected.
     *
     * @throws InvalidValueException
     */
    private static function text(string $entry): string
    {
        $entry = trim($entry);
        if ('' === $entry) {
            throw new InvalidValueException('Journal entry cannot be blank');
        }

        return $entry;
    }

    /**
     * @param list<TagId> $tagIds
     *
     * @return list<TagId> without duplicates, sorted by id so equal sets compare equal
     */
    private static function distinct(array $tagIds): array
    {
        $byId = [];
        foreach ($tagIds as $tagId) {
            $byId[$tagId->toRfc4122()] = $tagId;
        }
        $distinct = array_values($byId);
        usort($distinct, static fn (TagId $a, TagId $b) => $a->toRfc4122() <=> $b->toRfc4122());

        return $distinct;
    }
}
