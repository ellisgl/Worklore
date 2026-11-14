<?php

declare(strict_types=1);

namespace App\Journal;

use App\Company\ProjectId;
use App\Journal\Event\JournalEntryArchived;
use App\Journal\Event\JournalEntryMediaAttached;
use App\Journal\Event\JournalEntryMediaRemoved;
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
    /** @var array<string, Media> attached files by media id, oldest first */
    private array $media = [];

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
            TagId::distinct($tagIds),
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
        $tagIds = TagId::distinct($tagIds);
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

    /**
     * @return list<Media> attached files, oldest first
     */
    public function media(): array
    {
        return array_values($this->media);
    }

    /**
     * Attaching a file that is already attached (same media id) does nothing.
     */
    public function attach(Media $media, DateTimeImmutable $at): void
    {
        if (!isset($this->media[$media->id->toRfc4122()])) {
            $this->recordThat(new JournalEntryMediaAttached($this->id, $media->id, $media->filename, $media->mediaType, $media->size, $media->sha256, $at));
        }
    }

    /**
     * @return Media the file removed
     *
     * @throws MediaNotFound if no such file is attached
     */
    public function removeMedia(MediaId $mediaId, DateTimeImmutable $at): Media
    {
        $media = $this->media[$mediaId->toRfc4122()] ?? throw new MediaNotFound();
        $this->recordThat(new JournalEntryMediaRemoved($this->id, $mediaId, $at));

        return $media;
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
            $event instanceof JournalEntryMediaAttached => $this->media[$event->mediaId->toRfc4122()] = new Media($event->mediaId, $event->filename, $event->mediaType, $event->size, $event->sha256),
            $event instanceof JournalEntryMediaRemoved => $this->forget($event->mediaId),
            default => throw new LogicException('Not a journal entry event: '.$event::class),
        };
    }

    private function forget(MediaId $mediaId): void
    {
        unset($this->media[$mediaId->toRfc4122()]);
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
}
