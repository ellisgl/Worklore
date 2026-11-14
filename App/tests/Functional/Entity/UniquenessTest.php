<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\FindsEntities;
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

    public function testTagNameIsUniqueWithinCategory(): void
    {
        $user = $this->user('a@example.com');
        $category = $this->save(new CategoryEntity($user, 'Area'));
        $this->save($category->addTag('backend'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save($category->addTag('backend'));
    }

    public function testTagNameUniquenessIgnoresCase(): void
    {
        $user = $this->user('a@example.com');
        $category = $this->save(new CategoryEntity($user, 'Area'));
        $this->save($category->addTag('PHP'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->save($category->addTag('php'));
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
