<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Entity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Database-level rules from the XML mappings. A constraint violation aborts the
 * PostgreSQL transaction, so each test that expects one ends with it.
 */
final class UniquenessTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
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
        $this->save((new CompanyEntity($user, 'Acme'))->archive(new \DateTimeImmutable()));

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
        $this->save(new TagEntity($category, $user, 'backend'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save(new TagEntity($category, $user, 'backend'));
    }

    public function testSameTagNameAllowedInDifferentCategories(): void
    {
        $user = $this->user('a@example.com');
        $this->save(new TagEntity($this->save(new CategoryEntity($user, 'Area')), $user, 'backend'));
        $this->save(new TagEntity($this->save(new CategoryEntity($user, 'Skill')), $user, 'backend'));

        self::assertSame(2, $this->em->getRepository(TagEntity::class)->count(['name' => 'backend']));
    }

    public function testDeletingCategoryDeletesItsTags(): void
    {
        $user = $this->user('a@example.com');
        $category = $this->save(new CategoryEntity($user, 'Area'));
        $this->save(new TagEntity($category, $user, 'backend'));
        $this->save(new TagEntity($category, $user, 'frontend'));

        $this->em->remove($category);
        $this->em->flush();
        $this->em->clear();

        self::assertSame(0, $this->em->getRepository(TagEntity::class)->count([]));
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
