<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Entity\User;
use \DateTimeImmutable;
use \DateTimeZone;
use \InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Taxonomy/Config/TagEntity.orm.xml.
 */
final class TagEntity
{
    public private(set) Uuid $id;
    public private(set) string $name;
    public private(set) CategoryEntity $category;
    public private(set) ?DateTimeImmutable $archivedAt = null;

    public function __construct(
        CategoryEntity $category,
        public private(set) User $user,
        string $name,
        public private(set) DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        $this->id = Uuid::v7();
        $this->name = self::normalizeName($name);
        $this->category = $category;
        $category->addTag($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /**
     * Moves the tag to another category and updates both categories' tag lists.
     * The name must be free in the new category (uniq_tag_category_name).
     */
    public function setCategory(CategoryEntity $category): static
    {
        $previous = $this->category;
        if ($previous === $category) {
            return $this;
        }

        $this->category = $category;
        $previous->removeTag($this);
        $category->addTag($this);

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
            throw new InvalidArgumentException('Tag name cannot be blank');
        }

        return $name;
    }
}
