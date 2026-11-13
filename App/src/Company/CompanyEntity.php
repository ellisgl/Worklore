<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\Name;
use App\Shared\Owner;
use DateTimeImmutable;
use DateTimeZone;
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
    /**
     * Case-insensitive form of $name, for uniq_company_user_name; see
     * Name::key(). Only the database and CompanyRepository::nameExistsForOwner()
     * read it, so PHP code never does.
     *
     * @noinspection PhpPropertyOnlyWrittenInspection
     */
    private string $nameKey;
    private(set) ?string $overview;
    private(set) ?DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, ProductEntity> */
    private Collection $products;

    /** @var Collection<int, PositionEntity> */
    private Collection $positions;
    private(set) DateTimeImmutable $createdAt;

    /**
     * @param CompanyId|null $id pass one when the caller needs the id up front
     *                           (e.g. a command whose handler returns nothing)
     */
    public function __construct(
        private(set) readonly Owner $owner,
        string $name,
        ?string $overview = null,
        ?DateTimeImmutable $createdAt = null,
        ?CompanyId $id = null,
    ) {
        $this->id = $id ?? CompanyId::generate();
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
     * Adds a product to this company. Persisting the company saves it.
     */
    public function addProduct(string $name, ?string $overview = null, ?DateTimeImmutable $createdAt = null): ProductEntity
    {
        $product = new ProductEntity($this, $name, $overview, $createdAt);
        $this->products->add($product);

        return $product;
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
     * Records a position the owner held (or holds) here. Persisting the
     * company saves it.
     */
    public function addPosition(string $title, DateTimeImmutable $startDate, ?string $overview = null, ?DateTimeImmutable $createdAt = null): PositionEntity
    {
        $position = new PositionEntity($this, $title, $startDate, $overview, $createdAt);
        $this->positions->add($position);

        return $position;
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
