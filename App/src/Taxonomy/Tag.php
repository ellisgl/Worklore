<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Shared\EventSourcing\AggregateRoot;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\InvalidValueException;
use App\Shared\Name;
use App\Shared\UserId;
use App\Taxonomy\Event\TagArchived;
use App\Taxonomy\Event\TagCreated;
use App\Taxonomy\Event\TagMoved;
use App\Taxonomy\Event\TagRenamed;
use App\Taxonomy\Event\TagUnarchived;
use DateTimeImmutable;
use LogicException;

/**
 * A label in a category, e.g. "php" in "Skill". An event-sourced aggregate
 * (stream "tag"). Its category must be the owner's, and names are unique
 * within a category; the handlers check both.
 */
final class Tag extends AggregateRoot
{
    private TagId $id;
    private UserId $ownerId;
    private(set) CategoryId $categoryId;
    private(set) Name $name;
    private(set) ?DateTimeImmutable $archivedAt = null;

    private function __construct()
    {
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     */
    public static function create(TagId $id, CategoryId $categoryId, UserId $ownerId, string $name, DateTimeImmutable $at): self
    {
        $tag = new self();
        $tag->recordThat(new TagCreated($id, $categoryId, $ownerId, Name::of($name, 'Tag name')->value, $at));

        return $tag;
    }

    /**
     * @param iterable<DomainEvent> $history
     */
    public static function fromHistory(iterable $history): self
    {
        $tag = new self();
        $tag->replay($history);

        return $tag;
    }

    public function id(): TagId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     */
    public function rename(string $name): void
    {
        $name = Name::of($name, 'Tag name');
        if (!$name->equals($this->name)) {
            $this->recordThat(new TagRenamed($this->id, $name->value));
        }
    }

    public function moveTo(CategoryId $categoryId): void
    {
        if (!$categoryId->equals($this->categoryId)) {
            $this->recordThat(new TagMoved($this->id, $categoryId));
        }
    }

    /**
     * Archiving an archived tag keeps its original archive time.
     */
    public function archive(DateTimeImmutable $at): void
    {
        if (null === $this->archivedAt) {
            $this->recordThat(new TagArchived($this->id, $at));
        }
    }

    public function unarchive(): void
    {
        if (null !== $this->archivedAt) {
            $this->recordThat(new TagUnarchived($this->id));
        }
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof TagCreated => $this->created($event),
            $event instanceof TagRenamed => $this->name = Name::of($event->name, 'Tag name'),
            $event instanceof TagMoved => $this->categoryId = $event->categoryId,
            $event instanceof TagArchived => $this->archivedAt = $event->archivedAt,
            $event instanceof TagUnarchived => $this->archivedAt = null,
            default => throw new LogicException('Not a tag event: '.$event::class),
        };
    }

    private function created(TagCreated $event): void
    {
        $this->id = $event->tagId;
        $this->categoryId = $event->categoryId;
        $this->ownerId = $event->ownerId;
        $this->name = Name::of($event->name, 'Tag name');
    }
}
