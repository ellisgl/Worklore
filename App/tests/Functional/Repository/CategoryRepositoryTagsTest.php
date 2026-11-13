<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\TagEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CategoryRepositoryTagsTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CategoryRepository $tags;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->tags = self::getContainer()->get(CategoryRepository::class);
    }

    public function testReturnsUsersTagsOrderedByCategoryThenTagName(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $skill = $this->persist(new CategoryEntity($user, 'skill'));
        $area = $this->persist(new CategoryEntity($user, 'area'));
        // Inserted out of order on purpose
        $this->persist($skill->addTag('php'));
        $this->persist($area->addTag('frontend'));
        $this->persist($skill->addTag('go'));
        $this->persist($area->addTag('backend'));
        $this->em->flush();
        $this->em->clear();

        $result = $this->tags->getAllTagsByOwner($this->em->find(User::class, $user->id));

        self::assertSame(
            ['area/backend', 'area/frontend', 'skill/go', 'skill/php'],
            array_map(static fn (TagEntity $t) => $t->category->name->value.'/'.$t->name->value, $result),
        );
    }

    public function testExcludesOtherUsersTags(): void
    {
        $me = $this->persist(new User('me@example.com'));
        $other = $this->persist(new User('other@example.com'));
        $this->persist($this->persist(new CategoryEntity($me, 'area'))->addTag('mine'));
        $this->persist($this->persist(new CategoryEntity($other, 'area'))->addTag('theirs'));
        $this->em->flush();

        $names = array_map(static fn (TagEntity $t) => $t->name->value, $this->tags->getAllTagsByOwner($me));

        self::assertSame(['mine'], $names);
    }

    public function testCategoriesAreLoadedInTheSameQuery(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $this->persist($this->persist(new CategoryEntity($user, 'area'))->addTag('backend'));
        $this->em->flush();
        $this->em->clear();

        $tag = $this->tags->getAllTagsByOwner($this->em->find(User::class, $user->id))[0];

        self::assertFalse($this->em->getUnitOfWork()->isUninitializedObject($tag->category));
    }

    public function testArchivedTagsAndTagsInArchivedCategoriesAreExcludedByDefault(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $area = $this->persist(new CategoryEntity($user, 'area'));
        $old = $this->persist(new CategoryEntity($user, 'old')->archive(new DateTimeImmutable()));
        $this->persist($area->addTag('active'));
        $this->persist($area->addTag('archived')->archive(new DateTimeImmutable()));
        $this->persist($old->addTag('in-archived-category'));
        $this->em->flush();

        self::assertSame(['area/active'], $this->labels($this->tags->getAllTagsByOwner($user)));
    }

    public function testIncludeArchivedReturnsEverything(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $area = $this->persist(new CategoryEntity($user, 'area'));
        $old = $this->persist(new CategoryEntity($user, 'old')->archive(new DateTimeImmutable()));
        $this->persist($area->addTag('active'));
        $this->persist($area->addTag('archived')->archive(new DateTimeImmutable()));
        $this->persist($old->addTag('in-archived-category'));
        $this->em->flush();

        self::assertSame(
            ['area/active', 'area/archived', 'old/in-archived-category'],
            $this->labels($this->tags->getAllTagsByOwner($user, includeArchived: true)),
        );
    }

    public function testUserWithoutTagsGetsEmptyList(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $this->em->flush();

        self::assertSame([], $this->tags->getAllTagsByOwner($user));
    }

    /**
     * @param list<TagEntity> $tags
     *
     * @return list<string> "category/tag" pairs
     */
    private function labels(array $tags): array
    {
        return array_map(static fn (TagEntity $t) => $t->category->name->value.'/'.$t->name->value, $tags);
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
