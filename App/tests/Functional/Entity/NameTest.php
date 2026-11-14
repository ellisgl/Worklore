<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Shared\Name;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NameTest extends KernelTestCase
{
    public function testIsTrimmed(): void
    {
        self::assertSame('Acme Ltd', Name::of("  Acme Ltd \n")->value);
    }

    public function testBlankIsRejectedWithTheLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Company name cannot be blank');
        Name::of('   ', 'Company name');
    }

    public function testLengthCountsCharactersNotBytes(): void
    {
        self::assertSame(Name::MAX_LENGTH, mb_strlen(Name::of(str_repeat('é', Name::MAX_LENGTH))->value));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Tag name cannot be longer than 255 characters');
        Name::of(str_repeat('é', Name::MAX_LENGTH + 1), 'Tag name');
    }

    public function testKeyIgnoresCaseAndSurroundingSpace(): void
    {
        self::assertSame('acme ltd', Name::of('ACME Ltd')->key());
        self::assertSame(Name::of('Ünïcode')->key(), Name::keyOf('  üNÏCODE '));
    }

    public function testEqualsComparesTheTrimmedValue(): void
    {
        self::assertTrue(Name::of(' Acme ')->equals(Name::of('Acme')));
        self::assertFalse(Name::of('Acme')->equals(Name::of('acme')), 'Equality is exact; key() is for case-insensitive checks');
    }
}
