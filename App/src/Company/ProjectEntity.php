<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\User;
use App\Journal\JournalEntryEntity;
use App\Taxonomy\TagEntity;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Company/Config/ProjectEntity.orm.xml.
 */
final class ProjectEntity
{
    private(set) Uuid $id;
    private(set) string $name;
    private(set) ?string $overview;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, TagEntity> */
    private Collection $tags;

    /** @var Collection<int, JournalEntryEntity> */
    private Collection $journalEntries;

    /**
     * @throws DateMalformedStringException - I hate to have logic in my constructor, but it makes it easier.
     */
    public function __construct(
        private(set) readonly ProductEntity $product,
        private(set) readonly User $user,
        string $name,
        ?string $overview = null,
        private(set) readonly DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        $this->id = Uuid::v7();
        $this->name = self::normalizeName($name);
        $this->overview = self::normalizeOverview($overview);
        $this->tags = new ArrayCollection();
        $this->journalEntries = new ArrayCollection();
        $product->addProject($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
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
     * Keeps the inverse side in sync. Called by JournalEntryEntity's constructor;
     * create journal entry with `new JournalEntryEntity($project, ...)`, not this.
     *
     * @internal
     */
    public function addJournalEntry(JournalEntryEntity $entry): void
    {
        if ($entry->project !== $this) {
            throw new LogicException('Position belongs to a different company');
        }

        if (!$this->journalEntries->contains($entry)) {
            $this->journalEntries->add($entry);
        }
    }

    public function addTag(TagEntity $tag): self
    {
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
        $this->name = self::normalizeName($name);

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

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidArgumentException('Project name cannot be blank');
        }

        return $name;
    }

    /**
     * Blank or whitespace-only overviews are stored as null.
     */
    private static function normalizeOverview(?string $overview): ?string
    {
        return null === $overview ? null : (trim($overview) ?: null);
    }
}
