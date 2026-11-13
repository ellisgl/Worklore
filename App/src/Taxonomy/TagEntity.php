<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Shared\OwnershipMismatchException;
use App\Identity\User;
use App\Shared\Name;
use DateTimeImmutable;
use DateTimeZone;
use function Symfony\Component\Clock\now;

/**
 * Doctrine mapping: src/Taxonomy/Config/TagEntity.orm.xml.
 */
final class TagEntity
{
    private(set) TagId $id;
    private(set) Name $name;
    /** Case-insensitive form of $name, for uniq_tag_category_name; see Name::key() */
    private string $nameKey;
    private(set) CategoryEntity $category;
    private(set) ?DateTimeImmutable $archivedAt = null;
    private(set) DateTimeImmutable $createdAt;

    /**
     * @throws OwnershipMismatchException if the category belongs to another user
     */
    public function __construct(
        CategoryEntity $category,
        private(set) readonly User $user,
        string $name,
        ?DateTimeImmutable $createdAt = null,
    ) {
        OwnershipMismatchException::assertSameUser($category->user, $user, 'category');

        $this->id = TagId::generate();
        $this->createdAt = $createdAt ?? now()->setTimezone(new DateTimeZone('UTC'));
        $this->setName($name);
        $this->category = $category;
        $category->addTag($this);
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
        $this->name = Name::of($name, 'Tag name');
        $this->nameKey = $this->name->key();

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

}
