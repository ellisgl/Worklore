<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Shared\EntityId;

use function array_values;
use function ksort;

/**
 * Identifies a tag (the Tag aggregate and its stream).
 */
final readonly class TagId extends EntityId
{
    /**
     * @param list<self> $ids
     *
     * @return list<self> without duplicates, sorted by id, so equal sets compare equal
     */
    public static function distinct(array $ids): array
    {
        $byId = [];
        foreach ($ids as $id) {
            $byId[$id->toRfc4122()] = $id;
        }
        ksort($byId);

        return array_values($byId);
    }
}
