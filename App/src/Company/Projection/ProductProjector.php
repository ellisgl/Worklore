<?php

declare(strict_types=1);

namespace App\Company\Projection;

use App\Company\CompanyEvents;
use App\Company\Event\ProductArchived;
use App\Company\Event\ProductCreated;
use App\Company\Event\ProductOverviewChanged;
use App\Company\Event\ProductRenamed;
use App\Company\Event\ProductUnarchived;
use App\Shared\EventSourcing\Projector;
use App\Shared\EventSourcing\StoredEvent;
use App\Shared\EventSourcing\WritesRows;
use App\Shared\Name;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Projects product events into product, the tables ProductQueries reads.
 */
final readonly class ProductProjector implements Projector
{
    use WritesRows;

    public function __construct(private Connection $db)
    {
    }

    public function tables(): array
    {
        return ['product'];
    }

    public function forget(string $streamType, array $ids): void
    {
        if (CompanyEvents::PRODUCT === $streamType) {
            self::deleteIn($this->db, 'product', 'id', $ids);
        }
    }

    public function project(StoredEvent $event): void
    {
        $e = $event->event;
        match (true) {
            $e instanceof ProductCreated => $this->created($e),
            $e instanceof ProductRenamed => $this->update($e->productId->toRfc4122(), ['name' => $e->name, 'name_key' => Name::keyOf($e->name)]),
            $e instanceof ProductOverviewChanged => $this->update($e->productId->toRfc4122(), ['overview' => $e->overview]),
            $e instanceof ProductArchived => $this->update($e->productId->toRfc4122(), ['archived_at' => self::time($e->archivedAt)]),
            $e instanceof ProductUnarchived => $this->update($e->productId->toRfc4122(), ['archived_at' => null]),
            default => null,
        };
    }

    /**
     * @throws Exception
     */
    private function created(ProductCreated $e): void
    {
        $this->db->insert('product', [
            'id' => $e->productId->toRfc4122(),
            'company_id' => $e->companyId->toRfc4122(),
            'user_id' => $e->ownerId->toRfc4122(),
            'name' => $e->name,
            'name_key' => Name::keyOf($e->name),
            'overview' => $e->overview,
            'created_at' => self::time($e->createdAt),
            'archived_at' => null,
        ]);
    }

    /**
     * @param array<string, string|null> $columns
     *
     * @throws Exception
     */
    private function update(string $id, array $columns): void
    {
        $this->db->update('product', $columns, ['id' => $id]);
    }
}
