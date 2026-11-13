<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Entity\User;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Taxonomy/Config/TagEntity.orm.xml.
 */
final class TagEntity
{
    private(set) Uuid $id;
    private(set) string $name;
    private(set) CategoryEntity $category;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /**
     * @throws DateMalformedStringException - I hate to have logic in my constructor, but it make it easier.
     */
    public function __construct(
        CategoryEntity $category,
        private(set) readonly User $user,
        string $name,
        private(set) readonly DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
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
    public function setCategory(CategoryEntity $category): self
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

    public function setName(string $name): self
    {
        $this->name = self::normalizeName($name);

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
            throw new InvalidArgumentException('Tag name cannot be blank');
        }

        return $name;
    }
}
