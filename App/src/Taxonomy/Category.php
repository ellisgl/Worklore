<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Shared\EventSourcing\AggregateRoot;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\InvalidValueException;
use App\Shared\Name;
use App\Shared\Text;
use App\Shared\UserId;
use App\Taxonomy\Event\CategoryArchived;
use App\Taxonomy\Event\CategoryCreated;
use App\Taxonomy\Event\CategoryDescriptionChanged;
use App\Taxonomy\Event\CategoryRenamed;
use App\Taxonomy\Event\CategoryUnarchived;
use DateTimeImmutable;
use LogicException;

/**
 * A group of tags, e.g. "Skill". An event-sourced aggregate (stream
 * "category"); its tags are aggregates of their own.
 */
final class Category extends AggregateRoot
{
    private CategoryId $id;
    private UserId $ownerId;
    private(set) Name $name;
    private(set) ?string $description = null;
    private(set) ?DateTimeImmutable $archivedAt = null;

    private function __construct()
    {
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     */
    public static function create(CategoryId $id, UserId $ownerId, string $name, ?string $description, DateTimeImmutable $at): self
    {
        $category = new self();
        $category->recordThat(new CategoryCreated($id, $ownerId, Name::of($name, 'Category name')->value, Text::optional($description), $at));

        return $category;
    }

    /**
     * @param iterable<DomainEvent> $history
     */
    public static function fromHistory(iterable $history): self
    {
        $category = new self();
        $category->replay($history);

        return $category;
    }

    public function id(): CategoryId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     */
    public function rename(string $name): void
    {
        $name = Name::of($name, 'Category name');
        if (!$name->equals($this->name)) {
            $this->recordThat(new CategoryRenamed($this->id, $name->value));
        }
    }

    /**
     * Sets the description; blank clears it.
     */
    public function describe(?string $description): void
    {
        $description = Text::optional($description);
        if ($description !== $this->description) {
            $this->recordThat(new CategoryDescriptionChanged($this->id, $description));
        }
    }

    /**
     * Archiving an archived category keeps its original archive time.
     */
    public function archive(DateTimeImmutable $at): void
    {
        if (null === $this->archivedAt) {
            $this->recordThat(new CategoryArchived($this->id, $at));
        }
    }

    public function unarchive(): void
    {
        if (null !== $this->archivedAt) {
            $this->recordThat(new CategoryUnarchived($this->id));
        }
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof CategoryCreated => $this->created($event),
            $event instanceof CategoryRenamed => $this->name = Name::of($event->name, 'Category name'),
            $event instanceof CategoryDescriptionChanged => $this->description = $event->description,
            $event instanceof CategoryArchived => $this->archivedAt = $event->archivedAt,
            $event instanceof CategoryUnarchived => $this->archivedAt = null,
            default => throw new LogicException('Not a category event: '.$event::class),
        };
    }

    private function created(CategoryCreated $event): void
    {
        $this->id = $event->categoryId;
        $this->ownerId = $event->ownerId;
        $this->name = Name::of($event->name, 'Category name');
        $this->description = $event->description;
    }
}
