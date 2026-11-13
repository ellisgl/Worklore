<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Shared\Name;
use App\Shared\Owner;
use App\Shared\OwnershipMismatchException;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Clock\Clock;

/**
 * Doctrine mapping: src/Taxonomy/Config/TagEntity.orm.xml.
 */
final class TagEntity
{
    private(set) TagId $id;
    /** The aggregate owner, copied from the category; stored for owner-scoped queries */
    private(set) readonly Owner $owner;
    private(set) Name $name;
    /**
     * Case-insensitive form of $name, for uniq_tag_category_name; see
     * Name::key(). Only the database and CategoryRepository::nameExistsInCategory()
     * read it, so PHP code never does.
     *
     * @noinspection PhpPropertyOnlyWrittenInspection
     */
    private string $nameKey;
    private(set) CategoryEntity $category;
    private(set) ?DateTimeImmutable $archivedAt = null;
    private(set) DateTimeImmutable $createdAt;

    /**
     * @internal Create through CategoryEntity::addTag(), which adds it to the
     *           category's list; the owner is taken from the category.
     */
    public function __construct(
        CategoryEntity $category,
        string $name,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->owner = $category->owner;
        $this->id = TagId::generate();
        $this->createdAt = $createdAt ?? Clock::get()->now()->setTimezone(new DateTimeZone('UTC'));
        $this->setName($name);
        $this->category = $category;
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

        OwnershipMismatchException::assertSameOwner($category->owner, $this->owner, 'category');

        $this->category = $category;
        $previous->detachTag($this);
        $category->attachTag($this);

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
