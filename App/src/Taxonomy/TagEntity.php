<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Entity\OwnershipMismatchException;
use App\Entity\User;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Taxonomy/Config/TagEntity.orm.xml.
 */
final class TagEntity
{
    public const int MAX_NAME_LENGTH = 255;

    private(set) Uuid $id;
    private(set) string $name;
    /** Case-insensitive form of $name, for uniq_tag_category_name; see nameKey() */
    private string $nameKey;
    private(set) CategoryEntity $category;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /**
     * @throws OwnershipMismatchException if the category belongs to another user
     */
    public function __construct(
        CategoryEntity $category,
        private(set) readonly User $user,
        string $name,
        private(set) DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        OwnershipMismatchException::assertSameUser($category->user, $user, 'category');

        $this->id = Uuid::v7();
        $this->setName($name);
        $this->category = $category;
        $category->addTag($this);
    }

    /**
     * The form names are compared in for uniqueness: "PHP" and " php " clash.
     * TagRepository::nameExistsInCategory() uses this too.
     */
    public static function nameKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /**
     * Moves the tag to another category and updates both categories' tag lists.
     * The name must be free in the new category (uniq_tag_category_name).
     *
     * @throws OwnershipMismatchException if the category belongs to another user
     */
    public function setCategory(CategoryEntity $category): self
    {
        $previous = $this->category;
        if ($previous === $category) {
            return $this;
        }

        OwnershipMismatchException::assertSameUser($category->user, $this->user, 'category');

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
        $this->nameKey = self::nameKey($this->name);

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
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Tag name cannot be longer than %d characters', self::MAX_NAME_LENGTH));
        }

        return $name;
    }
}
