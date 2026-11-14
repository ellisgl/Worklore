<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyId;
use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\Doctrine\CategoryIdType;
use App\Taxonomy\TagId;
use App\Tests\Functional\FindsEntities;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\UuidV7;

final class TypedIdTest extends KernelTestCase
{
    use FindsEntities;

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

    /**
     * @throws ConversionException
     * @throws TypesException
     */
    public function testDoctrineTypeRejectsAnotherIdType(): void
    {
        self::bootKernel();
        $type = Type::getType(CategoryIdType::NAME);
        $platform = self::getContainer()->get('doctrine.dbal.default_connection')->getDatabasePlatform();

        $this->expectException(InvalidType::class);
        $type->convertToDatabaseValue(TagId::generate(), $platform);
    }

    /**
     * @throws ConversionException
     * @throws TypesException
     */
    public function testDoctrineTypeRejectsMalformedValueFromDatabase(): void
    {
        self::bootKernel();
        $platform = self::getContainer()->get('doctrine.dbal.default_connection')->getDatabasePlatform();

        $this->expectException(ValueNotConvertible::class);
        Type::getType(CategoryIdType::NAME)->convertToPHPValue('nope', $platform);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testEntityLoadsWithItsTypedId(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = new User('a@example.com');
        $company = new CategoryEntity($user, 'Area');
        $em->persist($user);
        $em->persist($company);
        $em->flush();
        $em->clear();

        // find() accepts the typed id, and also its string form.
        $byId = self::findOrFail($em, CategoryEntity::class, $company->id);
        self::assertTrue($company->id->equals($byId->id));
        self::assertSame($byId, $em->find(CategoryEntity::class, (string) $company->id), 'Same managed instance');
    }
}
