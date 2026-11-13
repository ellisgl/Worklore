<?php

declare(strict_types=1);

namespace App\Journal;

use App\Company\ProjectEntity;
use App\Entity\User;
use App\Taxonomy\TagEntity;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Journal/Config/JournalEntryEntity.orm.xml.
 */
final class JournalEntryEntity
{
    private(set) Uuid $id;
    private(set) string $title;
    private(set) ?string $entry;
    private(set) ?DateTimeImmutable $archivedAt = null;
    /** @var Collection<int, TagEntity> */
    private Collection $tags;

    /**
     * @throws DateMalformedStringException - I hate to have logic in my constructor, but it makes it easier.
     */
    public function __construct(
        private(set) readonly ProjectEntity $project,
        private(set) readonly User $user,
        string $title,
        ?string $entry = null,
        private(set) readonly DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        $this->id = Uuid::v7();
        $this->title = self::normalizeTitle($title);
        $this->entry = self::normalizeEntry($entry);
        $this->tags = new ArrayCollection();
        $project->addJournalEntry($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProject(): ProjectEntity
    {
        return $this->project;
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

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function addTag(TagEntity $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function archive(DateTimeImmutable $at): self
    {
        $this->archivedAt ??= $at;  // already archived: keep the original time

        return $this;
    }

    public function removeTag(TagEntity $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function setEntry(?string $entry): self
    {
        $this->entry = self::normalizeEntry($entry);

        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = self::normalizeTitle(title);

        return $this;
    }

    public function unarchive(): self
    {
        $this->archivedAt = null;

        return $this;
    }

    private static function normalizeTitle(string $title): string
    {
        $title = trim($title);
        if ('' === $title) {
            throw new InvalidArgumentException('Journal entry title cannot be blank');
        }

        return $title;
    }

    /**
     * Blank or whitespace-only entries are stored as null.
     */
    private static function normalizeEntry(?string $entry): ?string
    {
        return null === $entry ? null : (trim($entry) ?: null);
    }
}
