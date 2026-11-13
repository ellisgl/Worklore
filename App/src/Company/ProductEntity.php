<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\OwnershipMismatchException;
use App\Identity\User;
use App\Taxonomy\TagEntity;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use function Symfony\Component\Clock\now;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Company/Config/ProductEntity.orm.xml.
 */
final class ProductEntity
{
    public const int MAX_NAME_LENGTH = 255;

    private(set) Uuid $id;
    private(set) string $name;
    private(set) ?string $overview;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, ProjectEntity> */
    private Collection $projects;

    /** @var Collection<int, TagEntity> */
    private Collection $tags;
    private(set) DateTimeImmutable $createdAt;

    /**
     * @throws OwnershipMismatchException if the company belongs to another user
     */
    public function __construct(
        private(set) readonly CompanyEntity $company,
        private(set) readonly User $user,
        string $name,
        ?string $overview = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        OwnershipMismatchException::assertSameUser($company->user, $user, 'company');

        $this->id = Uuid::v7();
        $this->createdAt = $createdAt ?? now()->setTimezone(new DateTimeZone('UTC'));
        $this->name = self::normalizeName($name);
        $this->overview = self::normalizeOverview($overview);
        $this->projects = new ArrayCollection();
        $this->tags = new ArrayCollection();

        $company->addProduct($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /**
     * @return ReadableCollection<int, ProjectEntity>
     */
    public function getProjects(): ReadableCollection
    {
        return $this->projects;
    }

    /**
     * Keeps the inverse side in sync. Called by ProjectEntity's constructor;
     * create projects with `new ProjectEntity($product, ...)`, not this.
     *
     * @internal
     */
    public function addProject(ProjectEntity $project): void
    {
        if ($project->product !== $this) {
            throw new LogicException('Project belongs to a different product');
        }

        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
        }
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
        OwnershipMismatchException::assertSameUser($tag->user, $this->user, 'tag');

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
        $this->name = self::normalizeName($name);

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

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidArgumentException('Product name cannot be blank');
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Product name cannot be longer than %d characters', self::MAX_NAME_LENGTH));
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
