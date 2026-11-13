<?php

declare(strict_types=1);

namespace App\Company;

use App\Identity\User;
use App\Shared\Name;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use function Symfony\Component\Clock\now;

/**
 * Doctrine mapping: src/Company/Config/CompanyEntity.orm.xml.
 */
final class CompanyEntity
{
    private(set) CompanyId $id;
    private(set) Name $name;
    /** Case-insensitive form of $name, for uniq_company_user_name; see Name::key() */
    private string $nameKey;
    private(set) ?string $overview;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, ProductEntity> */
    private Collection $products;

    /** @var Collection<int, PositionEntity> */
    private Collection $positions;
    private(set) DateTimeImmutable $createdAt;

    public function __construct(
        private(set) readonly User $user,
        string $name,
        ?string $overview = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->id = CompanyId::generate();
        $this->createdAt = $createdAt ?? now()->setTimezone(new DateTimeZone('UTC'));
        $this->setName($name);
        $this->overview = self::normalizeOverview($overview);
        $this->products = new ArrayCollection();
        $this->positions = new ArrayCollection();
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
        $this->name = Name::of($name, 'Company name');
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
