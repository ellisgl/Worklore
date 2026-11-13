<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\Period;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PeriodTest extends TestCase
{
    public function testStartingOnIsOngoing(): void
    {
        $period = Period::startingOn(new DateTimeImmutable('2024-01-15'));

        self::assertTrue($period->isOngoing());
        self::assertNull($period->end);
    }

    public function testDaysOnly(): void
    {
        $period = Period::between(new DateTimeImmutable('2024-01-15 17:45'), new DateTimeImmutable('2024-02-01 09:30'));

        self::assertSame('2024-01-15 00:00', $period->start->format('Y-m-d H:i'));
        self::assertSame('2024-02-01 00:00', $period->end->format('Y-m-d H:i'));
    }

    public function testCannotEndBeforeStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Period::between(new DateTimeImmutable('2024-01-15'), new DateTimeImmutable('2024-01-14'));
    }

    public function testSameDayIsAllowedWhateverTheTime(): void
    {
        $period = Period::between(new DateTimeImmutable('2024-01-15 18:00'), new DateTimeImmutable('2024-01-15 08:00'));

        self::assertEquals($period->start, $period->end);
    }

    public function testChangesReturnNewPeriodsAndLeaveTheOriginal(): void
    {
        $original = Period::startingOn(new DateTimeImmutable('2024-01-15'));

        $ended = $original->endingOn(new DateTimeImmutable('2024-06-30'));
        $moved = $ended->withStart(new DateTimeImmutable('2024-02-01'));
        $reopened = $moved->reopened();

        self::assertTrue($original->isOngoing(), 'Original untouched');
        self::assertSame('2024-06-30', $ended->end->format('Y-m-d'));
        self::assertSame('2024-02-01', $moved->start->format('Y-m-d'));
        self::assertSame('2024-06-30', $moved->end->format('Y-m-d'));
        self::assertTrue($reopened->isOngoing());
        self::assertSame('2024-02-01', $reopened->start->format('Y-m-d'));
    }

    public function testCannotMoveStartPastTheEnd(): void
    {
        $period = Period::between(new DateTimeImmutable('2024-01-15'), new DateTimeImmutable('2024-06-30'));

        $this->expectException(InvalidArgumentException::class);
        $period->withStart(new DateTimeImmutable('2024-07-01'));
    }

    public function testEqualsComparesDays(): void
    {
        $a = Period::between(new DateTimeImmutable('2024-01-15 09:00'), new DateTimeImmutable('2024-06-30'));
        $b = Period::between(new DateTimeImmutable('2024-01-15 17:00'), new DateTimeImmutable('2024-06-30 23:59'));

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($a->reopened()));
    }
}
