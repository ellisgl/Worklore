<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Taxonomy\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TagRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TagRepository $tags;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->tags = self::getContainer()->get(TagRepository::class);
    }

    public function testReturnsUsersTagsOrderedByCategoryThenTagName(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $skill = $this->persist(new CategoryEntity($user, 'skill'));
        $area = $this->persist(new CategoryEntity($user, 'area'));
        // Inserted out of order on purpose
        $this->persist(new TagEntity($skill, $user, 'php'));
        $this->persist(new TagEntity($area, $user, 'frontend'));
        $this->persist(new TagEntity($skill, $user, 'go'));
        $this->persist(new TagEntity($area, $user, 'backend'));
        $this->em->flush();
        $this->em->clear();

        $result = $this->tags->getAllTagsByUser($this->em->find(User::class, $user->getId()));

        self::assertSame(
            ['area/backend', 'area/frontend', 'skill/go', 'skill/php'],
            array_map(static fn (TagEntity $t) => $t->category->name.'/'.$t->name, $result),
        );
    }

    public function testExcludesOtherUsersTags(): void
    {
        $me = $this->persist(new User('me@example.com'));
        $other = $this->persist(new User('other@example.com'));
        $this->persist(new TagEntity($this->persist(new CategoryEntity($me, 'area')), $me, 'mine'));
        $this->persist(new TagEntity($this->persist(new CategoryEntity($other, 'area')), $other, 'theirs'));
        $this->em->flush();

        $names = array_map(static fn (TagEntity $t) => $t->name, $this->tags->getAllTagsByUser($me));

        self::assertSame(['mine'], $names);
    }

    public function testCategoriesAreLoadedInTheSameQuery(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $this->persist(new TagEntity($this->persist(new CategoryEntity($user, 'area')), $user, 'backend'));
        $this->em->flush();
        $this->em->clear();

        $tag = $this->tags->getAllTagsByUser($this->em->find(User::class, $user->getId()))[0];

        self::assertFalse($this->em->getUnitOfWork()->isUninitializedObject($tag->category));
    }

    public function testArchivedTagsAndTagsInArchivedCategoriesAreExcludedByDefault(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $area = $this->persist(new CategoryEntity($user, 'area'));
        $old = $this->persist((new CategoryEntity($user, 'old'))->archive(new \DateTimeImmutable()));
        $this->persist(new TagEntity($area, $user, 'active'));
        $this->persist((new TagEntity($area, $user, 'archived'))->archive(new \DateTimeImmutable()));
        $this->persist(new TagEntity($old, $user, 'in-archived-category'));
        $this->em->flush();

        self::assertSame(['area/active'], $this->labels($this->tags->getAllTagsByUser($user)));
    }

    public function testIncludeArchivedReturnsEverything(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $area = $this->persist(new CategoryEntity($user, 'area'));
        $old = $this->persist((new CategoryEntity($user, 'old'))->archive(new \DateTimeImmutable()));
        $this->persist(new TagEntity($area, $user, 'active'));
        $this->persist((new TagEntity($area, $user, 'archived'))->archive(new \DateTimeImmutable()));
        $this->persist(new TagEntity($old, $user, 'in-archived-category'));
        $this->em->flush();

        self::assertSame(
            ['area/active', 'area/archived', 'old/in-archived-category'],
            $this->labels($this->tags->getAllTagsByUser($user, includeArchived: true)),
        );
    }

    public function testUserWithoutTagsGetsEmptyList(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $this->em->flush();

        self::assertSame([], $this->tags->getAllTagsByUser($user));
    }

    /**
     * @param list<TagEntity> $tags
     *
     * @return list<string> "category/tag" pairs
     */
    private function labels(array $tags): array
    {
        return array_map(static fn (TagEntity $t) => $t->category->name.'/'.$t->name, $tags);
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
