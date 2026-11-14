<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\ByteSize;
use App\Shared\InvalidValueException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ByteSizeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function sizes(): iterable
    {
        yield 'bytes' => ['300', 300];
        yield 'kilobytes' => ['512K', 512 * 1024];
        yield 'megabytes, lowercase with B' => ['25mb', 25 * 1024 ** 2];
        yield 'gigabytes, spaced' => [' 1 G ', 1024 ** 3];
        yield 'terabytes' => ['2T', 2 * 1024 ** 4];
    }

    #[DataProvider('sizes')]
    public function testParses(string $size, int $bytes): void
    {
        self::assertSame($bytes, ByteSize::parse($size));
    }

    public function testRejectsWhatIsNotASize(): void
    {
        foreach (['', 'lots', '1.5G', '-1M', '10X'] as $size) {
            try {
                ByteSize::parse($size);
                self::fail($size.' parsed');
            } catch (InvalidValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testFormatsForPeople(): void
    {
        self::assertSame('1 byte', ByteSize::format(1));
        self::assertSame('300 bytes', ByteSize::format(300));
        self::assertSame('1.5 KB', ByteSize::format(1536));
        self::assertSame('25 MB', ByteSize::format(25 * 1024 ** 2));
        self::assertSame('1.5 GB', ByteSize::format(3 * 1024 ** 3 / 2));
    }
}
