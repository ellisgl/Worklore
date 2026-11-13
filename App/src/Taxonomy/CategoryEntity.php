<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Entity\User;
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
 * Doctrine mapping: src/Taxonomy/Config/CategoryEntity.orm.xml.
 */
final class CategoryEntity
{
    public private(set) Uuid $id;
    public private(set) string $name;
    public private(set) ?string $description;
    public private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, TagEntity> */
    private Collection $tags;

    /**
     * @throws DateMalformedStringException - I hate to have logic in my constructor, but it make it easier.
     */
    public function __construct(
        public private(set) readonly User $user,
        string $name,
        ?string $description = null,
        public private(set) readonly DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        $this->id = Uuid::v7();
        $this->name = self::normalizeName($name);
        $this->description = self::normalizeDescription($description);
        $this->tags = new ArrayCollection();
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

    /**
     * Keeps the inverse side in sync. Called by TagEntity's constructor and
     * TagEntity::setCategory(); use those, not this.
     *
     * @internal
     */
    public function addTag(TagEntity $tag): void
    {
        if ($tag->category !== $this) {
            throw new LogicException('Tag belongs to a different category');
        }

        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
    }

    /**
     * @internal Called by TagEntity::setCategory().
     */
    public function removeTag(TagEntity $tag): void
    {
        if ($tag->category === $this) {
            throw new LogicException('Use TagEntity::setCategory() to move a tag out of a category');
        }

        $this->tags->removeElement($tag);
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

    public function setDescription(?string $description): self
    {
        $this->description = self::normalizeDescription($description);

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
            throw new InvalidArgumentException('Category name cannot be blank');
        }

        return $name;
    }

    /**
     * Blank or whitespace-only descriptions are stored as null.
     */
    private static function normalizeDescription(?string $description): ?string
    {
        return null === $description ? null : (trim($description) ?: null);
    }
}
