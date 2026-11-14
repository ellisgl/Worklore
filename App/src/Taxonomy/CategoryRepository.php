<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Shared\EventSourcing\AggregateStore;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\UserId;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Categories, loaded from and saved to their event streams.
 */
final readonly class CategoryRepository
{
    public function __construct(private AggregateStore $store)
    {
    }

    /**
     * The category, if it exists and belongs to the owner; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too.
     *
     * @throws Exception
     */
    public function findForOwner(CategoryId $id, UserId $ownerId): ?Category
    {
        $history = $this->store->history(TaxonomyEvents::CATEGORY, $id);
        if ([] === $history) {
            return null;
        }
        $category = Category::fromHistory($history);

        return $category->ownerId()->equals($ownerId) ? $category : null;
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function save(Category $category): void
    {
        $this->store->save(TaxonomyEvents::CATEGORY, $category);
    }
}
