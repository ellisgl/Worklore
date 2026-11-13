<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Identity\User;
use App\Shared\InvalidValueException;
use PHPUnit\Framework\TestCase;

final class UserEntityTest extends TestCase
{
    public function testEmailIsTrimmedAndLowercased(): void
    {
        $user = new User('  Someone@Example.COM ');

        self::assertSame('someone@example.com', $user->email);
        self::assertSame('someone@example.com', $user->getUserIdentifier());
    }

    public function testBlankEmailIsRejected(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessageIs('Email cannot be blank');

        new User('   ');
    }

    public function testChangingToABlankEmailKeepsTheOldOne(): void
    {
        $user = new User('someone@example.com');

        try {
            $user->setEmail('');
            self::fail('Expected a blank email to be rejected');
        } catch (InvalidValueException) {
        }

        self::assertSame('someone@example.com', $user->email);
    }
}
