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

/**
 * Doctrine mapping: src/Company/Config/ProductEntity.orm.xml.
 */
final class ProductEntity
{
    private(set) ProductId $id;
    /** The aggregate owner, copied from the company; stored for owner-scoped queries */
    private(set) readonly Owner $owner;
    private(set) Name $name;
    private(set) ?string $overview;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, ProjectEntity> */
    private Collection $projects;

    /** @var Collection<int, TagEntity> */
    private Collection $tags;
    private(set) DateTimeImmutable $createdAt;

    /**
     * @internal create through CompanyEntity::addProduct(), which adds it to the
     *           company's list; the owner is taken from the company
     */
    public function __construct(
        private(set) readonly CompanyEntity $company,
        string $name,
        ?string $overview = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->owner = $company->owner;
        $this->id = ProductId::generate();
        $this->createdAt = $createdAt ?? Clock::get()->now()->setTimezone(new DateTimeZone('UTC'));
        $this->name = Name::of($name, 'Product name');
        $this->overview = self::normalizeOverview($overview);
        $this->projects = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    /**
     * @return ReadableCollection<int, ProjectEntity>
     */
    public function getProjects(): ReadableCollection
    {
        return $this->projects;
    }

    /**
     * Starts a project for this product. Persisting the company (or this
     * product) saves it.
     */
    public function addProject(string $name, ?string $overview = null, ?DateTimeImmutable $createdAt = null): ProjectEntity
    {
        $project = new ProjectEntity($this, $name, $overview, $createdAt);
        $this->projects->add($project);

        return $project;
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
        $this->name = Name::of($name, 'Product name');

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
