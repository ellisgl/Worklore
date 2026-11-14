<?php

declare(strict_types=1);

namespace App\Company;

use App\Company\Event\ProductArchived;
use App\Company\Event\ProductCreated;
use App\Company\Event\ProductOverviewChanged;
use App\Company\Event\ProductRenamed;
use App\Company\Event\ProductUnarchived;
use App\Shared\EventSourcing\AggregateRoot;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\InvalidValueException;
use App\Shared\Name;
use App\Shared\Text;
use App\Shared\UserId;
use DateTimeImmutable;
use LogicException;

/**
 * Something a company makes; its projects belong to it. Its company must be the owner's (the handlers check). An event-sourced aggregate (stream "product").
 */
final class Product extends AggregateRoot
{
    private ProductId $id;
    private UserId $ownerId;
    private(set) CompanyId $companyId;
    private(set) Name $name;
    private(set) ?string $overview = null;
    private(set) ?DateTimeImmutable $archivedAt = null;

    private function __construct()
    {
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     */
    public static function create(
        ProductId $id,
        CompanyId $companyId,
        UserId $ownerId,
        string $name,
        ?string $overview,
        DateTimeImmutable $at,
    ): self {
        $product = new self();
        $product->recordThat(new ProductCreated($id, $companyId, $ownerId, Name::of($name, 'Product name')->value, Text::optional($overview), $at));

        return $product;
    }

    /**
     * @param iterable<DomainEvent> $history
     */
    public static function fromHistory(iterable $history): self
    {
        $product = new self();
        $product->replay($history);

        return $product;
    }

    public function id(): ProductId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     */
    public function rename(string $name): void
    {
        $name = Name::of($name, 'Product name');
        if (!$name->equals($this->name)) {
            $this->recordThat(new ProductRenamed($this->id, $name->value));
        }
    }

    /**
     * Sets the overview; blank clears it.
     */
    public function describe(?string $overview): void
    {
        $overview = Text::optional($overview);
        if ($overview !== $this->overview) {
            $this->recordThat(new ProductOverviewChanged($this->id, $overview));
        }
    }

    /**
     * Archiving an archived product keeps its original archive time.
     */
    public function archive(DateTimeImmutable $at): void
    {
        if (null === $this->archivedAt) {
            $this->recordThat(new ProductArchived($this->id, $at));
        }
    }

    public function unarchive(): void
    {
        if (null !== $this->archivedAt) {
            $this->recordThat(new ProductUnarchived($this->id));
        }
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof ProductCreated => $this->created($event),
            $event instanceof ProductRenamed => $this->name = Name::of($event->name, 'Product name'),
            $event instanceof ProductOverviewChanged => $this->overview = $event->overview,
            $event instanceof ProductArchived => $this->archivedAt = $event->archivedAt,
            $event instanceof ProductUnarchived => $this->archivedAt = null,
            default => throw new LogicException('Not a product event: '.$event::class),
        };
    }

    private function created(ProductCreated $event): void
    {
        $this->id = $event->productId;
        $this->companyId = $event->companyId;
        $this->ownerId = $event->ownerId;
        $this->name = Name::of($event->name, 'Product name');
        $this->overview = $event->overview;
    }
}
