<?php

declare(strict_types=1);

namespace App\Company;

use App\Company\Event\ProjectArchived;
use App\Company\Event\ProjectCreated;
use App\Company\Event\ProjectOverviewChanged;
use App\Company\Event\ProjectRenamed;
use App\Company\Event\ProjectRetagged;
use App\Company\Event\ProjectUnarchived;
use App\Shared\EventSourcing\AggregateRoot;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\InvalidValueException;
use App\Shared\Name;
use App\Shared\Text;
use App\Shared\UserId;
use App\Taxonomy\TagId;
use DateTimeImmutable;
use LogicException;

/**
 * A piece of work on a product, labelled with tags. Its product and tags must be the owner's (the handlers check). An event-sourced aggregate (stream "project").
 */
final class Project extends AggregateRoot
{
    private ProjectId $id;
    private UserId $ownerId;
    private(set) ProductId $productId;
    private(set) Name $name;
    private(set) ?string $overview = null;
    /** @var list<TagId> sorted by id */
    private(set) array $tagIds = [];
    private(set) ?DateTimeImmutable $archivedAt = null;

    private function __construct()
    {
    }

    /**
     * @param list<TagId> $tagIds duplicates are dropped
     *
     * @throws InvalidValueException if the name is blank or too long
     */
    public static function create(
        ProjectId $id,
        ProductId $productId,
        UserId $ownerId,
        string $name,
        ?string $overview,
        array $tagIds,
        DateTimeImmutable $at,
    ): self {
        $project = new self();
        $project->recordThat(new ProjectCreated($id, $productId, $ownerId, Name::of($name, 'Project name')->value, Text::optional($overview), TagId::distinct($tagIds), $at));

        return $project;
    }

    /**
     * @param iterable<DomainEvent> $history
     */
    public static function fromHistory(iterable $history): self
    {
        $project = new self();
        $project->replay($history);

        return $project;
    }

    public function id(): ProjectId
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
        $name = Name::of($name, 'Project name');
        if (!$name->equals($this->name)) {
            $this->recordThat(new ProjectRenamed($this->id, $name->value));
        }
    }

    /**
     * Sets the overview; blank clears it.
     */
    public function describe(?string $overview): void
    {
        $overview = Text::optional($overview);
        if ($overview !== $this->overview) {
            $this->recordThat(new ProjectOverviewChanged($this->id, $overview));
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
            $this->recordThat(new ProjectRetagged($this->id, $tagIds));
        }
    }

    /**
     * Archiving an archived project keeps its original archive time.
     */
    public function archive(DateTimeImmutable $at): void
    {
        if (null === $this->archivedAt) {
            $this->recordThat(new ProjectArchived($this->id, $at));
        }
    }

    public function unarchive(): void
    {
        if (null !== $this->archivedAt) {
            $this->recordThat(new ProjectUnarchived($this->id));
        }
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof ProjectCreated => $this->created($event),
            $event instanceof ProjectRenamed => $this->name = Name::of($event->name, 'Project name'),
            $event instanceof ProjectOverviewChanged => $this->overview = $event->overview,
            $event instanceof ProjectRetagged => $this->tagIds = $event->tagIds,
            $event instanceof ProjectArchived => $this->archivedAt = $event->archivedAt,
            $event instanceof ProjectUnarchived => $this->archivedAt = null,
            default => throw new LogicException('Not a project event: '.$event::class),
        };
    }

    private function created(ProjectCreated $event): void
    {
        $this->id = $event->projectId;
        $this->productId = $event->productId;
        $this->ownerId = $event->ownerId;
        $this->name = Name::of($event->name, 'Project name');
        $this->overview = $event->overview;
        $this->tagIds = $event->tagIds;
    }
}
