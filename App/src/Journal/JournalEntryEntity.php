<?php

declare(strict_types=1);

namespace App\Journal;

use App\Company\ProjectEntity;
use App\Entity\User;
use App\Taxonomy\TagEntity;
use DateMalformedStringException;
use \DateTimeImmutable;
use \DateTimeZone;
use \InvalidArgumentException;
use \LogicException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Journal/Config/JournalEntity.orm.xml.
 */
final class JournalEntryEntity
{
    public private(set) Uuid $id;
    public private(set) string $name;
    public private(set) ?string $entry;
    public private(set) ?DateTimeImmutable $archivedAt = null;
    /** @var Collection<int, TagEntity> */
    private Collection $tags;

    /**
     * @throws DateMalformedStringException - I hate to have logic in my constructor, but it make it easier.
     */
    public function __construct(
        public private(set) readonly ProjectEntity $project,
        public private(set) readonly User $user,
        string $name,
        ?string $entry = null,
        public private(set) readonly DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        $this->id = Uuid::v7();
        $this->name = self::normalizeName($name);
        $this->entry = self::normalizeEntry($entry);
        $this->tags = new ArrayCollection();
        $project->addJournalEntry($this);
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

    public function setEntry(?string $entry): self
    {
        $this->entry = self::normalizeEntry($entry);

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
            throw new InvalidArgumentException('Journal entry cannot be blank');
        }

        return $name;
    }

    /**
     * Blank or whitespace-only entries are stored as null.
     */
    private static function normalizeEntry(?string $entry): ?string
    {
        return null === $entry ? null : (trim($entry) ?: null);
    }
}
