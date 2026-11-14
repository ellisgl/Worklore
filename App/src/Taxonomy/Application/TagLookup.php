<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\Owner;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\TagEntity;
use App\Taxonomy\TagId;

/**
 * Turns the tag ids a command carries into the owner's tags (for journal
 * entries and projects).
 */
final readonly class TagLookup
{
    public function __construct(private CategoryRepository $categories)
    {
    }

    /**
     * @param list<TagId> $ids
     *
     * @return list<TagEntity> in the order given, duplicates dropped
     *
     * @throws TagNotFound if any id isn't one of the owner's tags
     */
    public function ownedBy(Owner $owner, array $ids): array
    {
        $tags = [];
        foreach ($ids as $id) {
            $tags[$id->toRfc4122()] ??= $this->categories->findTagByIdForOwner($id, $owner) ?? throw new TagNotFound();
        }

        return array_values($tags);
    }

    /**
     * The same check, for aggregates that hold tag ids rather than tags.
     *
     * @param list<TagId> $ids
     *
     * @return list<TagId> in the order given, duplicates dropped
     *
     * @throws TagNotFound if any id isn't one of the owner's tags
     */
    public function idsOwnedBy(Owner $owner, array $ids): array
    {
        $tagIds = [];
        foreach ($this->ownedBy($owner, $ids) as $tag) {
            $tagIds[] = $tag->id;
        }

        return $tagIds;
    }
}
