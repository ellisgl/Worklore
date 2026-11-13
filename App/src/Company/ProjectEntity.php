<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\User;
use App\Taxonomy\TagEntity;
use \DateTimeImmutable;
use \DateTimeZone;
use \InvalidArgumentException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Company/Config/ProjectEntity.orm.xml.
 */
final class ProjectEntity
{
    public private(set) Uuid $id;
    public private(set) string $name;
    public private(set) ?string $overview;
    public private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, TagEntity> */
    private Collection $tags;

    public function __construct(
        public private(set) ProductEntity $product,
        public private(set) User $user,
        string $name,
        ?string $overview = null,
        public private(set) DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        $this->id = Uuid::v7();
        $this->name = self::normalizeName($name);
        $this->overview = self::normalizeOverview($overview);
        $this->tags = new ArrayCollection();

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

    public function addTag(TagEntity $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(TagEntity $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function setName(string $name): static
    {
        $this->name = self::normalizeName($name);

        return $this;
    }

    public function setOverview(?string $overview): static
    {
        $this->overview = self::normalizeOverview($overview);

        return $this;
    }

    public function archive(DateTimeImmutable $at): static
    {
        $this->archivedAt ??= $at;  // already archived: keep the original time

        return $this;
    }

    public function unarchive(): static
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
