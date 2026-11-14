<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Column formats for projectors: times as UTC timestamps, days as dates.
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
}
