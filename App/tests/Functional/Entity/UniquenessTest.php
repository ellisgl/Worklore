<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\FindsEntities;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Database-level rules from the XML mappings. A constraint violation aborts the
 * PostgreSQL transaction, so each test that expects one ends with it.
 */
final class UniquenessTest extends KernelTestCase
{
    use FindsEntities;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCompanyNameIsUniquePerUser(): void
    {
        $user = $this->user('a@example.com');
        $this->save(new CompanyEntity($user, 'Acme'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save(new CompanyEntity($user, 'Acme'));
    }

    public function testArchivedCompanyStillReservesItsName(): void
    {
        $user = $this->user('a@example.com');
        $this->save(new CompanyEntity($user, 'Acme')->archive(new DateTimeImmutable()));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save(new CompanyEntity($user, 'Acme'));
    }

    public function testDifferentUsersCanUseTheSameCompanyName(): void
    {
        $this->save(new CompanyEntity($this->user('a@example.com'), 'Acme'));
        $this->save(new CompanyEntity($this->user('b@example.com'), 'Acme'));

        self::assertSame(2, $this->em->getRepository(CompanyEntity::class)->count(['name' => 'Acme']));
    }

    public function testTagNameIsUniqueWithinCategory(): void
    {
        $user = $this->user('a@example.com');
        $category = $this->save(new CategoryEntity($user, 'Area'));
        $this->save($category->addTag('backend'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save($category->addTag('backend'));
    }

    public function testCompanyNameUniquenessIgnoresCase(): void
    {
        $user = $this->user('a@example.com');
        $this->save(new CompanyEntity($user, 'Acme'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save(new CompanyEntity($user, 'ACME'));
    }

    public function testTagNameUniquenessIgnoresCase(): void
    {
        $user = $this->user('a@example.com');
        $category = $this->save(new CategoryEntity($user, 'Area'));
        $this->save($category->addTag('PHP'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save($category->addTag('php'));
    }

    public function testRenameKeepsNameKeyInSync(): void
    {
        $user = $this->user('a@example.com');
        $acme = $this->save(new CompanyEntity($user, 'Acme'));
        $acme->setName('Globex');
        $this->em->flush();

        // "acme" is free again, "globex" is taken
        $this->save(new CompanyEntity($user, 'ACME'));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->save(new CompanyEntity($user, 'GLOBEX'));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testCategoryDescriptionIsUnlimitedText(): void
    {
        $user = $this->user('a@example.com');
        $category = $this->save(new CategoryEntity($user, 'Area', str_repeat('x', 5000)));
        $this->em->clear();

        $description = self::findOrFail($this->em, CategoryEntity::class, $category->id)->description;
        self::assertNotNull($description);
        self::assertSame(5000, mb_strlen($description));
    }

    public function testSameTagNameAllowedInDifferentCategories(): void
    {
        $user = $this->user('a@example.com');
        $this->save($this->save(new CategoryEntity($user, 'Area'))->addTag('backend'));
        $this->save($this->save(new CategoryEntity($user, 'Skill'))->addTag('backend'));

        self::assertSame(2, $this->em->getRepository(TagEntity::class)->count(['name' => 'backend']));
    }

    public function testDeletingCategoryDeletesItsTags(): void
    {
        $user = $this->user('a@example.com');
        $category = $this->save(new CategoryEntity($user, 'Area'));
        $this->save($category->addTag('backend'));
        $this->save($category->addTag('frontend'));

        $this->em->remove($category);
        $this->em->flush();
        $this->em->clear();

        self::assertSame(0, $this->em->getRepository(TagEntity::class)->count());
    }

    public function testProductNameIsUniqueWithinCompanyIgnoringCase(): void
    {
        $user = $this->user('a@example.com');
        $acme = $this->save(new CompanyEntity($user, 'Acme'));
        $this->save($acme->addProduct('Widget'));
        $this->save(new CompanyEntity($user, 'Globex')->addProduct('Widget')->company);  // fine elsewhere

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save($acme->addProduct('WIDGET'));
    }

    public function testProjectNameIsUniqueWithinProductIgnoringCase(): void
    {
        $acme = $this->save(new CompanyEntity($this->user('a@example.com'), 'Acme'));
        $widget = $this->save($acme->addProduct('Widget'));
        $this->save($widget->addProject('Launch'));
        $this->save($acme->addProduct('Gadget')->addProject('Launch'));  // fine elsewhere

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save($widget->addProject('LAUNCH'));
    }

    public function testCategoryNameIsUniquePerUserIgnoringCase(): void
    {
        $user = $this->user('a@example.com');
        $this->save(new CategoryEntity($user, 'Skill'));
        $this->save(new CategoryEntity($this->user('b@example.com'), 'Skill'));  // fine for someone else

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save(new CategoryEntity($user, 'SKILL'));
    }

    private function user(string $email): User
    {
        return $this->save(new User($email));
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function save(object $entity): object
    {
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }
}
