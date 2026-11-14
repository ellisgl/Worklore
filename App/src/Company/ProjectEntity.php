<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\Name;
use App\Shared\Owner;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\TagEntity;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Clock\Clock;

use function in_array;

/**
 * Doctrine mapping: src/Company/Config/ProjectEntity.orm.xml.
 */
final class ProjectEntity
{
    private(set) ProjectId $id;
    /** The aggregate owner, copied from the product; stored for owner-scoped queries */
    private(set) readonly Owner $owner;
    private(set) Name $name;
    /**
     * Case-insensitive form of $name, for uniq_project_product_name; see
     * Name::key(). Only the database and CompanyRepository::projectNameExistsInProduct()
     * read it, so PHP code never does.
     *
     * @noinspection PhpPropertyOnlyWrittenInspection
     */
    private string $nameKey;
    private(set) ?string $overview;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, TagEntity> */
    private Collection $tags;

    private(set) DateTimeImmutable $createdAt;

    /**
     * @internal create through ProductEntity::addProject(), which adds it to the
     *           product's list; the owner is taken from the product
     */
    public function __construct(
        private(set) readonly ProductEntity $product,
        string $name,
        ?string $overview = null,
        ?DateTimeImmutable $createdAt = null,
        ?ProjectId $id = null,
    ) {
        $this->owner = $product->owner;
        $this->id = $id ?? ProjectId::generate();
        $this->createdAt = $createdAt ?? Clock::get()->now()->setTimezone(new DateTimeZone('UTC'));
        $this->setName($name);
        $this->overview = self::normalizeOverview($overview);
        $this->tags = new ArrayCollection();
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

    /**
     * @throws OwnershipMismatchException if the tag belongs to another user
     */
    public function addTag(TagEntity $tag): self
    {
        OwnershipMismatchException::assertSameOwner($tag->owner, $this->owner, 'tag');

        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    /**
     * Replaces the project's tags with exactly these (duplicates ignored). All
     * of them are checked before anything changes.
     *
     * @throws OwnershipMismatchException if a tag belongs to another user
     */
    public function setTags(TagEntity ...$tags): self
    {
        foreach ($tags as $tag) {
            OwnershipMismatchException::assertSameOwner($tag->owner, $this->owner, 'tag');
        }

        foreach ($this->tags->toArray() as $current) {
            if (!in_array($current, $tags, true)) {
                $this->tags->removeElement($current);
            }
        }
        foreach ($tags as $tag) {
            $this->addTag($tag);
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
        $this->name = Name::of($name, 'Project name');
        $this->nameKey = $this->name->key();

        return $this;
    }

    public function setOverview(?string $overview): self
    {
        $this->overview = self::normalizeOverview($overview);

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
     * Blank or whitespace-only overviews are stored as null.
     */
    private static function normalizeOverview(?string $overview): ?string
    {
        return null === $overview ? null : (trim($overview) ?: null);
    }
}
