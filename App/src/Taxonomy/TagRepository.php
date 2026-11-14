<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Shared\EventSourcing\AggregateStore;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\UserId;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Tags, loaded from and saved to their event streams.
 */
final readonly class TagRepository
{
    public function __construct(private AggregateStore $store)
    {
    }

    /**
     * The tag, if it exists and belongs to the owner; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too.
     *
     * @throws Exception
     */
    public function findForOwner(TagId $id, UserId $ownerId): ?Tag
    {
        $history = $this->store->history(TaxonomyEvents::TAG, $id);
        if ([] === $history) {
            return null;
        }
        $tag = Tag::fromHistory($history);

        return $tag->ownerId()->equals($ownerId) ? $tag : null;
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function save(Tag $tag): void
    {
        $this->store->save(TaxonomyEvents::TAG, $tag);
    }
}
