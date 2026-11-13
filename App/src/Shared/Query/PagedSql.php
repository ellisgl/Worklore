<?php

declare(strict_types=1);

namespace App\Shared\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use LogicException;

use function is_int;
use function is_numeric;
use function sprintf;

/**
 * SQL helpers for query classes that return a Paginated list.
 */
final class PagedSql
{
    /**
     * The LIMIT/OFFSET clause for a page. Both are ints from Page, so they
     * are safe to inline.
     */
    public static function limit(Page $page): string
    {
        return sprintf(' LIMIT %d OFFSET %d', $page->size, $page->offset());
    }

    /**
     * Counts the rows of "SELECT count(*) $fromWhere": the list's FROM and
     * WHERE clauses, without ORDER BY.
     *
     * @param array<string, mixed> $params
     *
     * @throws Exception
     */
    public static function count(Connection $db, string $fromWhere, array $params): int
    {
        $count = $db->fetchOne('SELECT count(*) '.$fromWhere, $params);
        if (is_int($count)) {
            return $count;
        }
        if (is_numeric($count)) {
            return (int) $count;
        }

        throw new LogicException('count(*) returned no number');
    }
}
