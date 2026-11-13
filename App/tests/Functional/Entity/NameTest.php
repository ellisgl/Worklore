<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Identity\User;
use App\Shared\Doctrine\NameType;
use App\Shared\Name;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NameTest extends KernelTestCase
{
    public function testIsTrimmed(): void
    {
        self::assertSame('Acme Ltd', Name::of("  Acme Ltd \n")->value);
    }

    public function testBlankIsRejectedWithTheLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Company name cannot be blank');
        Name::of('   ', 'Company name');
    }

    public function testLengthCountsCharactersNotBytes(): void
    {
        self::assertSame(Name::MAX_LENGTH, mb_strlen(Name::of(str_repeat('é', Name::MAX_LENGTH))->value));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag name cannot be longer than 255 characters');
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

    public function testDoctrineTypeRoundTrip(): void
    {
        self::bootKernel();
        $platform = self::getContainer()->get('doctrine.dbal.default_connection')->getDatabasePlatform();
        $type = Type::getType(NameType::NAME);

        self::assertSame('Acme', $type->convertToDatabaseValue(Name::of('Acme'), $platform));
        self::assertTrue(Name::of('Acme')->equals($type->convertToPHPValue('Acme', $platform)));
        self::assertNull($type->convertToPHPValue(null, $platform));
    }

    public function testDoctrineTypeRejectsBlankParameter(): void
    {
        self::bootKernel();
        $platform = self::getContainer()->get('doctrine.dbal.default_connection')->getDatabasePlatform();

        $this->expectException(ValueNotConvertible::class);
        Type::getType(NameType::NAME)->convertToDatabaseValue('  ', $platform);
    }

    public function testDoctrineTypeRejectsOtherValues(): void
    {
        self::bootKernel();
        $platform = self::getContainer()->get('doctrine.dbal.default_connection')->getDatabasePlatform();

        $this->expectException(InvalidType::class);
        Type::getType(NameType::NAME)->convertToDatabaseValue(42, $platform);
    }

    public function testStringCriteriaWorkInQueries(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);
        $user = new User('a@example.com');
        $em->persist($user);
        $em->persist(new CompanyEntity($user, 'Acme'));
        $em->flush();
        $em->clear();

        $found = $em->getRepository(CompanyEntity::class)->findOneBy(['name' => ' Acme ']);

        self::assertNotNull($found);
        self::assertInstanceOf(Name::class, $found->name);
    }
}
