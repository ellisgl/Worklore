<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\User;
use DateMalformedStringException;
use \DateTimeImmutable;
use \DateTimeZone;
use \InvalidArgumentException;
use \LogicException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Company/Config/CompanyEntity.orm.xml.
 */
final class CompanyEntity
{
    public private(set) Uuid $id;
    public private(set) string $name;
    public private(set) ?string $overview;
    public private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, ProductEntity> */
    private Collection $products;

    /** @var Collection<int, PositionEntity> */
    private Collection $positions;

    /**
     * @throws DateMalformedStringException - I hate to have logic in my constructor, but it make it easier.
     */
    public function __construct(
        public private(set) readonly User $user,
        string $name,
        ?string $overview = null,
        public private(set) readonly DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        $this->id = Uuid::v7();
        $this->name = self::normalizeName($name);
        $this->overview = self::normalizeOverview($overview);
        $this->products = new ArrayCollection();
        $this->positions = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /**
     * @return ReadableCollection<int, ProductEntity>
     */
    public function getProducts(): ReadableCollection
    {
        return $this->products;
    }

    /**
     * Keeps the inverse side in sync. Called by ProductEntity's constructor;
     * create products with `new ProductEntity($company, ...)`, not this.
     *
     * @internal
     */
    public function addProduct(ProductEntity $product): void
    {
        if ($product->company !== $this) {
            throw new LogicException('Product belongs to a different company');
        }

        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }
    }

    /**
     * Positions held at this company, newest start date first when loaded
     * from the database.
     *
     * @return ReadableCollection<int, PositionEntity>
     */
    public function getPositions(): ReadableCollection
    {
        return $this->positions;
    }

    /**
     * Keeps the inverse side in sync. Called by PositionEntity's constructor;
     * create positions with `new PositionEntity($company, ...)`, not this.
     *
     * @internal
     */
    public function addPosition(PositionEntity $position): void
    {
        if ($position->company !== $this) {
            throw new LogicException('Position belongs to a different company');
        }

        if (!$this->positions->contains($position)) {
            $this->positions->add($position);
        }
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
            throw new InvalidArgumentException('Company name cannot be blank');
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
