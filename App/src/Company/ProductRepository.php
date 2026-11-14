<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\EventSourcing\AggregateStore;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\UserId;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Products, loaded from and saved to their event streams.
 */
final readonly class ProductRepository
{
    public function __construct(private AggregateStore $store)
    {
    }

    /**
     * The product, if it exists and belongs to the owner; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too.
     *
     * @throws Exception
     */
    public function findForOwner(ProductId $id, UserId $ownerId): ?Product
    {
        $history = $this->store->history(CompanyEvents::PRODUCT, $id);
        if ([] === $history) {
            return null;
        }
        $product = Product::fromHistory($history);

        return $product->ownerId()->equals($ownerId) ? $product : null;
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function save(Product $product): void
    {
        $this->store->save(CompanyEvents::PRODUCT, $product);
    }
}
