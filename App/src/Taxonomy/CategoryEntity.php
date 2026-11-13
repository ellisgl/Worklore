<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Identity\User;
use App\Shared\Name;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use function Symfony\Component\Clock\now;

/**
 * Doctrine mapping: src/Taxonomy/Config/CategoryEntity.orm.xml.
 */
final class CategoryEntity
{
    private(set) CategoryId $id;
    private(set) Name $name;
    private(set) ?string $description;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, TagEntity> */
    private Collection $tags;
    private(set) DateTimeImmutable $createdAt;

    public function __construct(
        private(set) readonly User $user,
        string $name,
        ?string $description = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->id = CategoryId::generate();
        $this->createdAt = $createdAt ?? now()->setTimezone(new DateTimeZone('UTC'));
        $this->name = Name::of($name, 'Category name');
        $this->description = self::normalizeDescription($description);
        $this->tags = new ArrayCollection();
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
        $this->name = Name::of($name, 'Category name');

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

    /**
     * Blank or whitespace-only descriptions are stored as null.
     */
    private static function normalizeDescription(?string $description): ?string
    {
        return null === $description ? null : (trim($description) ?: null);
    }
}
