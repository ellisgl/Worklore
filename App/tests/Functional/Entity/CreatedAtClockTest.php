<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use Closure;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * createdAt defaults to Symfony's clock, so tests (and anything else that
 * sets the clock) control it.
 */
final class CreatedAtClockTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    /**
     * @return iterable<string, array{Closure(User, ?DateTimeImmutable): DateTimeImmutable}>
     */
    public static function entityProvider(): iterable
    {
        yield 'user' => [static fn (User $u, ?DateTimeImmutable $at) => new User('b@example.com', $at)->createdAt];
        yield 'category' => [static fn (User $u, ?DateTimeImmutable $at) => new CategoryEntity($u, 'Area', null, $at)->createdAt];
        yield 'tag' => [static fn (User $u, ?DateTimeImmutable $at) => new CategoryEntity($u, 'Area')->addTag('php', $at)->createdAt];
    }

    /**
     * @param Closure(User, ?DateTimeImmutable): DateTimeImmutable $create
     */
    #[DataProvider('entityProvider')]
    public function testCreatedAtComesFromTheClock(Closure $create): void
    {
        self::mockTime('2026-01-02 03:04:05 UTC');

        $createdAt = $create(new User('a@example.com'), null);

        self::assertSame('2026-01-02 03:04:05 UTC', $createdAt->format('Y-m-d H:i:s T'));
    }

    /**
     * @param Closure(User, ?DateTimeImmutable): DateTimeImmutable $create
     */
    #[DataProvider('entityProvider')]
    public function testCreatedAtIsStoredInUtc(Closure $create): void
    {
        self::mockTime('2026-01-02 12:00:00 Europe/Paris');

        $createdAt = $create(new User('a@example.com'), null);

        self::assertSame('2026-01-02 11:00:00 UTC', $createdAt->format('Y-m-d H:i:s T'));
    }

    /**
     * @param Closure(User, ?DateTimeImmutable): DateTimeImmutable $create
     *
     * @throws DateMalformedStringException
     */
    #[DataProvider('entityProvider')]
    public function testExplicitCreatedAtIsKept(Closure $create): void
    {
        $at = new DateTimeImmutable('2020-05-06 07:08:09', new DateTimeZone('UTC'));

        self::assertSame($at, $create(new User('a@example.com'), $at));
    }
}
