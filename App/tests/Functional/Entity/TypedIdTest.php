<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Company\CompanyId;
use App\Company\Doctrine\CompanyIdType;
use App\Identity\User;
use App\Taxonomy\TagId;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\UuidV7;

final class TypedIdTest extends KernelTestCase
{
    public function testGeneratedIdsAreUuidV7AndUnique(): void
    {
        $a = CompanyId::generate();
        $b = CompanyId::generate();

        self::assertInstanceOf(UuidV7::class, $a->uuid);
        self::assertFalse($a->equals($b));
    }

    public function testRoundTripsThroughString(): void
    {
        $id = CompanyId::generate();

        self::assertTrue($id->equals(CompanyId::fromString((string) $id)));
    }

    public function testIdsOfDifferentTypesAreNeverEqual(): void
    {
        $company = CompanyId::generate();

        self::assertFalse($company->equals(TagId::fromString((string) $company)), 'Same UUID, different type');
    }

    public function testInvalidStringIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CompanyId::fromString('not-a-uuid');
    }

    public function testDoctrineTypeRejectsAnotherIdType(): void
    {
        self::bootKernel();
        $type = Type::getType(CompanyIdType::NAME);
        $platform = self::getContainer()->get('doctrine.dbal.default_connection')->getDatabasePlatform();

        $this->expectException(InvalidType::class);
        $type->convertToDatabaseValue(TagId::generate(), $platform);
    }

    public function testDoctrineTypeRejectsMalformedValueFromDatabase(): void
    {
        self::bootKernel();
        $platform = self::getContainer()->get('doctrine.dbal.default_connection')->getDatabasePlatform();

        $this->expectException(ValueNotConvertible::class);
        Type::getType(CompanyIdType::NAME)->convertToPHPValue('nope', $platform);
    }

    public function testEntityLoadsWithItsTypedId(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);
        $user = new User('a@example.com');
        $company = new CompanyEntity($user, 'Acme');
        $em->persist($user);
        $em->persist($company);
        $em->flush();
        $em->clear();

        // find() accepts the typed id, and also its string form.
        $byId = $em->find(CompanyEntity::class, $company->id);
        self::assertInstanceOf(CompanyId::class, $byId->id);
        self::assertTrue($company->id->equals($byId->id));
        self::assertSame($byId, $em->find(CompanyEntity::class, (string) $company->id), 'Same managed instance');
    }
}
