<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Query;

use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    /**
     * @throws InvalidValueException
     */
    public function testDefaultsAndOffset(): void
    {
        $first = new Page();
        $third = new Page(3, 20);

        self::assertSame([1, Page::DEFAULT_SIZE, 0], [$first->number, $first->size, $first->offset()]);
        self::assertSame(40, $third->offset());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'page 0' => [0, 10];
        yield 'negative page' => [-1, 10];
        yield 'size 0' => [1, 0];
        yield 'size over the max' => [1, Page::MAX_SIZE + 1];
    }

    #[DataProvider('invalidProvider')]
    public function testRejectsOutOfRangeValues(int $number, int $size): void
    {
        $this->expectException(InvalidValueException::class);

        new Page($number, $size);
    }
}
