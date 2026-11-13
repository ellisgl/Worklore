<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Identity\User;
use App\Journal\JournalEntryEntity;
use App\Taxonomy\CategoryEntity;
use Closure;
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
     * @return iterable<string, array{Closure(User $u, ?DateTimeImmutable $at): object}>
     */
    public static function entityProvider(): iterable
    {
        yield 'user' => [static fn (User $u, ?DateTimeImmutable $at) => new User('b@example.com', $at)];
        yield 'company' => [static fn (User $u, ?DateTimeImmutable $at) => new CompanyEntity($u, 'Acme', null, $at)];
        yield 'product' => [static fn (User $u, ?DateTimeImmutable $at) => new CompanyEntity($u, 'Acme')->addProduct('Widget', null, $at)];
        yield 'project' => [static fn (User $u, ?DateTimeImmutable $at) => new CompanyEntity($u, 'Acme')->addProduct('Widget')->addProject('Launch', null, $at)];
        yield 'position' => [static fn (User $u, ?DateTimeImmutable $at) => new CompanyEntity($u, 'Acme')->addPosition('Dev', new DateTimeImmutable('2024-01-01'), null, $at)];
        yield 'journal entry' => [static fn (User $u, ?DateTimeImmutable $at) => new JournalEntryEntity(new CompanyEntity($u, 'Acme')->addProduct('Widget')->addProject('Launch'), $u, 'Kickoff', 'Body', $at)];
        yield 'category' => [static fn (User $u, ?DateTimeImmutable $at) => new CategoryEntity($u, 'Area', null, $at)];
        yield 'tag' => [static fn (User $u, ?DateTimeImmutable $at) => new CategoryEntity($u, 'Area')->addTag('php', $at)];
    }

    #[DataProvider('entityProvider')]
    public function testCreatedAtComesFromTheClock(Closure $create): void
    {
        self::mockTime('2026-01-02 03:04:05 UTC');

        $entity = $create(new User('a@example.com'), null);

        self::assertSame('2026-01-02 03:04:05 UTC', $entity->createdAt->format('Y-m-d H:i:s T'));
    }

    #[DataProvider('entityProvider')]
    public function testCreatedAtIsStoredInUtc(Closure $create): void
    {
        self::mockTime('2026-01-02 12:00:00 Europe/Paris');

        $entity = $create(new User('a@example.com'), null);

        self::assertSame('2026-01-02 11:00:00 UTC', $entity->createdAt->format('Y-m-d H:i:s T'));
    }

    #[DataProvider('entityProvider')]
    public function testExplicitCreatedAtIsKept(Closure $create): void
    {
        $at = new DateTimeImmutable('2020-05-06 07:08:09', new DateTimeZone('UTC'));

        $entity = $create(new User('a@example.com'), $at);

        self::assertSame($at, $entity->createdAt);
    }
}
