<?php

declare(strict_types=1);

namespace App\Company\Query;

use App\Company\CompanyId;
use App\Company\ProductId;
use App\Company\ProjectId;
use App\Shared\Name;
use App\Shared\UserId;
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
readonly class CompanyNames
{
    public function __construct(private Connection $db)
    {
    }

    /**
     * @throws Exception
     */
    public function companyNameTaken(UserId $ownerId, string $name, ?CompanyId $except = null): bool
    {
        return $this->taken('company', 'user_id', $ownerId->toRfc4122(), $name, $except?->toRfc4122());
    }

    /**
     * @throws Exception
     */
    public function productNameTaken(CompanyId $companyId, string $name, ?ProductId $except = null): bool
    {
        return $this->taken('product', 'company_id', $companyId->toRfc4122(), $name, $except?->toRfc4122());
    }

    /**
     * @throws Exception
     */
    public function projectNameTaken(ProductId $productId, string $name, ?ProjectId $except = null): bool
    {
        return $this->taken('project', 'product_id', $productId->toRfc4122(), $name, $except?->toRfc4122());
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
