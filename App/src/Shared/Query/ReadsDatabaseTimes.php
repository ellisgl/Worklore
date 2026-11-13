<?php

declare(strict_types=1);

namespace App\Shared\Query;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Timestamps and dates come back from SQL as strings, stored in UTC without
 * a zone.
 */
trait ReadsDatabaseTimes
{
    /**
     * @throws DateMalformedStringException
     */
    private static function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    /**
     * @throws DateMalformedStringException
     */
    private static function utcOrNull(?string $value): ?DateTimeImmutable
    {
        return null === $value ? null : self::utc($value);
    }
}
