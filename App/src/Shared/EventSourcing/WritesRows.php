<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * For projectors: column formats (times as UTC timestamps, days as dates),
 * and deleting rows when records are purged.
 */
trait WritesRows
{
    private static function time(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function date(DateTimeImmutable $day): string
    {
        return $day->format('Y-m-d');
    }

    /**
     * @param list<string> $ids
     *
     * @throws Exception
     */
    private static function deleteIn(Connection $db, string $table, string $column, array $ids): void
    {
        $db->executeStatement("DELETE FROM $table WHERE $column IN (:ids)", ['ids' => $ids], ['ids' => ArrayParameterType::STRING]);
    }
}
