<?php

declare(strict_types=1);

namespace App\Company\Query;

use App\Company\CompanyId;
use App\Company\ProductId;
use App\Shared\UserId;
use DateMalformedStringException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Read side for products: SQL shaped for the API, returning read models.
 *
 * @phpstan-type ProductRow array{id: string, company_id: string, name: string, overview: string|null, created_at: string, archived_at: string|null, project_count: int}
 */
final readonly class ProductQueries
{
    use ReadsDatabaseTimes;

    /** Every column a ProductSummary needs; callers add WHERE and ORDER BY. */
    private const string SELECT = <<<'SQL'
        SELECT p.id,
               p.company_id,
               p.name,
               p.overview,
               p.created_at,
               p.archived_at,
               (SELECT count(*)
                  FROM project pj
                 WHERE pj.product_id = p.id
                   AND pj.archived_at IS NULL) AS project_count
          FROM product p
          JOIN company c ON c.id = p.company_id
        SQL;

    public function __construct(private Connection $db)
    {
    }

    /**
     * The owner's products at a company, sorted by name.
     *
     * By default archived products, and every product of an archived
     * company, are left out; pass $includeArchived = true for all.
     *
     * @return list<ProductSummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function listForCompany(UserId $ownerId, CompanyId $companyId, bool $includeArchived = false): array
    {
        $sql = self::SELECT.' WHERE p.user_id = :owner AND p.company_id = :company';
        if (!$includeArchived) {
            $sql .= ' AND p.archived_at IS NULL AND c.archived_at IS NULL';
        }
        $sql .= ' ORDER BY p.name, p.id';

        /** @var list<ProductRow> $rows */
        $rows = $this->db->fetchAllAssociative($sql, ['owner' => $ownerId->toRfc4122(), 'company' => $companyId->toRfc4122()]);

        $products = [];
        foreach ($rows as $row) {
            $products[] = self::summary($row);
        }

        return $products;
    }

    /**
     * One of the owner's products, archived or not; null if it doesn't exist
     * or belongs to someone else.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function findForOwner(UserId $ownerId, ProductId $productId): ?ProductSummary
    {
        /** @var ProductRow|false $row */
        $row = $this->db->fetchAssociative(
            self::SELECT.' WHERE p.user_id = :owner AND p.id = :id',
            ['owner' => $ownerId->toRfc4122(), 'id' => $productId->toRfc4122()],
        );

        return false === $row ? null : self::summary($row);
    }

    /**
     * @param ProductRow $row
     *
     * @throws DateMalformedStringException
     */
    private static function summary(array $row): ProductSummary
    {
        return new ProductSummary(
            id: $row['id'],
            companyId: $row['company_id'],
            name: $row['name'],
            overview: $row['overview'],
            createdAt: self::utc($row['created_at']),
            archivedAt: self::utcOrNull($row['archived_at']),
            projectCount: (int) $row['project_count'],
        );
    }
}
