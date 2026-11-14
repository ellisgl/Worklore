<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\UserId;
use App\Taxonomy\TagId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

use function array_map;
use function count;
use function is_int;
use function is_string;

/**
 * Checks that the tag ids a command carries are the owner's tags (for
 * journal entries and projects), against the tag projection.
 */
final readonly class TagLookup
{
    public function __construct(private Connection $db)
    {
    }

    /**
     * @param list<TagId> $ids
     *
     * @return list<TagId> distinct, sorted by id
     *
     * @throws Exception
     * @throws TagNotFound if any id isn't one of the owner's tags
     */
    public function idsOwnedBy(UserId $ownerId, array $ids): array
    {
        $ids = TagId::distinct($ids);
        if ([] === $ids) {
            return [];
        }

        $found = $this->db->fetchOne(
            'SELECT count(*) FROM tag WHERE user_id = :owner AND id IN (:ids)',
            ['owner' => $ownerId->toRfc4122(), 'ids' => array_map(static fn (TagId $id) => $id->toRfc4122(), $ids)],
            ['ids' => ArrayParameterType::STRING],
        );
        if (count($ids) !== (is_int($found) || is_string($found) ? (int) $found : -1)) {
            throw new TagNotFound();
        }

        return $ids;
    }
}
