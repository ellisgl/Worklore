<?php

declare(strict_types=1);

namespace App\Company;

use App\Journal\JournalEntryEntity;
use App\Shared\Name;
use App\Shared\Owner;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\TagEntity;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;
use Symfony\Component\Clock\Clock;

/**
 * Doctrine mapping: src/Company/Config/ProjectEntity.orm.xml.
 */
final class ProjectEntity
{
    private(set) ProjectId $id;
    /** The aggregate owner, copied from the product; stored for owner-scoped queries */
    private(set) readonly Owner $owner;
    private(set) Name $name;
    private(set) ?string $overview;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, TagEntity> */
    private Collection $tags;

    /** @var Collection<int, JournalEntryEntity> */
    private Collection $journalEntries;
    private(set) DateTimeImmutable $createdAt;

    /**
     * @internal Create through ProductEntity::addProject(), which adds it to the
     *           product's list; the owner is taken from the product.
     */
    public function __construct(
        private(set) readonly ProductEntity $product,
        string $name,
        ?string $overview = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->owner = $product->owner;
        $this->id = ProjectId::generate();
        $this->createdAt = $createdAt ?? Clock::get()->now()->setTimezone(new DateTimeZone('UTC'));
        $this->name = Name::of($name, 'Project name');
        $this->overview = self::normalizeOverview($overview);
        $this->tags = new ArrayCollection();
        $this->journalEntries = new ArrayCollection();
    }

    /**
     * @return ReadableCollection<int, TagEntity>
     */
    public function getTags(): ReadableCollection
    {
        return $this->tags;
    }

    public function hasTag(TagEntity $tag): bool
    {
        return $this->tags->contains($tag);
    }

    /**
     * @return ReadableCollection<int, JournalEntryEntity>
     */
    public function getJournalEntries(): ReadableCollection
    {
        return $this->journalEntries;
    }

    /**
     * Keeps the inverse side in sync. Called by JournalEntryEntity's constructor;
     * create journal entry with `new JournalEntryEntity($project, ...)`, not this.
     *
     * @internal
     */
    public function addJournalEntry(JournalEntryEntity $entry): void
    {
        if ($entry->project !== $this) {
            throw new LogicException('Journal entry belongs to a different project');
        }

        if (!$this->journalEntries->contains($entry)) {
            $this->journalEntries->add($entry);
        }
    }

    /**
     * @throws OwnershipMismatchException if the tag belongs to another user
     */
    public function addTag(TagEntity $tag): self
    {
        OwnershipMismatchException::assertSameOwner($tag->owner, $this->owner, 'tag');

        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(TagEntity $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function setName(string $name): self
    {
        $this->name = Name::of($name, 'Project name');

        return $this;
    }

    public function setOverview(?string $overview): self
    {
        $this->overview = self::normalizeOverview($overview);

        return $this;
    }

    public function archive(DateTimeImmutable $at): self
    {
        $this->archivedAt ??= $at;  // already archived: keep the original time

        return $this;
    }

    public function unarchive(): self
    {
        $this->archivedAt = null;

        return $this;
    }

    /**
     * Blank or whitespace-only overviews are stored as null.
     */
    private static function normalizeOverview(?string $overview): ?string
    {
        return null === $overview ? null : (trim($overview) ?: null);
    }
}
