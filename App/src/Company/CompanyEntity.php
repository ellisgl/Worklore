<?php

declare(strict_types=1);

namespace App\Company;

use App\Identity\User;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Company/Config/CompanyEntity.orm.xml.
 */
final class CompanyEntity
{
    public const int MAX_NAME_LENGTH = 255;

    private(set) Uuid $id;
    private(set) string $name;
    /** Case-insensitive form of $name, for uniq_company_user_name; see nameKey() */
    private string $nameKey;
    private(set) ?string $overview;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, ProductEntity> */
    private Collection $products;

    /** @var Collection<int, PositionEntity> */
    private Collection $positions;

    public function __construct(
        private(set) readonly User $user,
        string $name,
        ?string $overview = null,
        private(set) DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        $this->id = Uuid::v7();
        $this->setName($name);
        $this->overview = self::normalizeOverview($overview);
        $this->products = new ArrayCollection();
        $this->positions = new ArrayCollection();
    }

    /**
     * The form names are compared in for uniqueness: "Acme" and " ACME " clash.
     * CompanyRepository::nameExistsForUser() uses this too.
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
        $this->nameKey = self::nameKey($this->name);

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
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Company name cannot be longer than %d characters', self::MAX_NAME_LENGTH));
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
