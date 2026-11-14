<?php

declare(strict_types=1);

namespace App\Taxonomy\Query;

use App\Shared\Name;
use App\Shared\UserId;
use App\Taxonomy\CategoryId;
use App\Taxonomy\TagId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Whether a name is free, checked against the projections before a command
 * records it: case-insensitive (name_key), archived records included. The
 * unique constraints on the projections stay as the backstop for two
 * requests racing each other.
 *
 * Not final: NameRaceTest replaces it to let a race through.
 */
readonly class TaxonomyNames
{
    public function __construct(private Connection $db)
    {
    }

    /**
     * @throws Exception
     */
    public function categoryNameTaken(UserId $ownerId, string $name, ?CategoryId $except = null): bool
    {
        return $this->taken('category', 'user_id', $ownerId->toRfc4122(), $name, $except?->toRfc4122());
    }

    /**
     * @throws Exception
     */
    public function tagNameTaken(CategoryId $categoryId, string $name, ?TagId $except = null): bool
    {
        return $this->taken('tag', 'category_id', $categoryId->toRfc4122(), $name, $except?->toRfc4122());
    }

    /**
     * @throws Exception
     */
    private function taken(string $table, string $scope, string $scopeId, string $name, ?string $except): bool
    {
        return false !== $this->db->fetchOne(
            "SELECT 1 FROM $table WHERE $scope = :scope AND name_key = :key AND id IS DISTINCT FROM :except",
            ['scope' => $scopeId, 'key' => Name::keyOf($name), 'except' => $except],
        );
    }
}
