<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\TagEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CategoryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CategoryRepository $categories;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->categories = self::getContainer()->get(CategoryRepository::class);
    }

    public function testReturnsUsersCategoriesOrderedByName(): void
    {
        $user = $this->persist(new User('a@example.com'));

        // Inserted out of order on purpose
        $this->persist(new CategoryEntity($user, 'skill'));
        $this->persist(new CategoryEntity($user, 'area'));
        $this->persist(new CategoryEntity($user, 'location'));

        $this->em->flush();
        $this->em->clear();

        $result = $this->categories->getAllCategoriesByUser($this->em->find(User::class, $user->getId()));

        self::assertSame(['area', 'location', 'skill'], $this->names($result));
    }

    public function testExcludesOtherUsersCategories(): void
    {
        $me = $this->persist(new User('me@example.com'));
        $other = $this->persist(new User('other@example.com'));
        $this->persist(new CategoryEntity($me, 'mine'));
        $this->persist(new CategoryEntity($other, 'theirs'));
        $this->em->flush();

        self::assertSame(['mine'], $this->names($this->categories->getAllCategoriesByUser($me)));
    }

    public function testArchivedCategoriesAreExcludedByDefault(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $this->persist(new CategoryEntity($user, 'active'));
        $this->persist((new CategoryEntity($user, 'archived'))->archive(new \DateTimeImmutable()));
        $this->em->flush();

        self::assertSame(['active'], $this->names($this->categories->getAllCategoriesByUser($user)));
    }

    public function testIncludeArchivedReturnsEverything(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $this->persist(new CategoryEntity($user, 'active'));
        $this->persist((new CategoryEntity($user, 'archived'))->archive(new \DateTimeImmutable()));
        $this->em->flush();

        self::assertSame(
            ['active', 'archived'],
            $this->names($this->categories->getAllCategoriesByUser($user, includeArchived: true)),
        );
    }

    public function testCategoriesAreReturnedWhetherOrNotTheyHaveTags(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $withTags = $this->persist(new CategoryEntity($user, 'area'));
        $this->persist(new TagEntity($withTags, $user, 'backend'));
        $this->persist(new TagEntity($withTags, $user, 'frontend'));
        $this->persist(new CategoryEntity($user, 'empty'));
        $this->em->flush();

        // Each category appears once, however many tags it has
        self::assertSame(['area', 'empty'], $this->names($this->categories->getAllCategoriesByUser($user)));
    }

    public function testUserWithoutCategoriesGetsEmptyList(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $this->em->flush();

        self::assertSame([], $this->categories->getAllCategoriesByUser($user));
    }

    /**
     * @param list<CategoryEntity> $categories
     *
     * @return list<string>
     */
    private function names(array $categories): array
    {
        return array_map(static fn (CategoryEntity $c) => $c->name, $categories);
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persist(object $entity): object
    {
        $this->em->persist($entity);

        return $entity;
    }
}
