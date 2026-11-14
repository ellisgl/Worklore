<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\FindsEntities;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The Company and Taxonomy aggregates: children are created through their
 * root and saved with it.
 */
final class AggregateTest extends KernelTestCase
{
    use FindsEntities;

    private EntityManagerInterface $em;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->user = new User('a@example.com');
        $this->em->persist($this->user);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testPersistingTheCategorySavesItsTags(): void
    {
        $category = new CategoryEntity($this->user, 'Area');
        $category->addTag('backend');
        $category->addTag('frontend');

        $this->em->persist($category);  // only the root
        $this->em->flush();
        $this->em->clear();

        self::assertCount(2, self::findOrFail($this->em, CategoryEntity::class, $category->id)->getTags());
    }

    /**
     * Regression: with both categories reloaded, their tag lists aren't loaded
     * until the move touches them, and loading the old one used to reset the
     * tag's category.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testMovingASavedTagBetweenReloadedCategories(): void
    {
        $area = new CategoryEntity($this->user, 'Area');
        $skill = new CategoryEntity($this->user, 'Skill');
        $tag = $area->addTag('php');
        $area->addTag('sql');
        $this->em->persist($area);
        $this->em->persist($skill);
        $this->em->flush();
        $this->em->clear();

        $loadedTag = self::findOrFail($this->em, TagEntity::class, $tag->id);
        $loadedSkill = self::findOrFail($this->em, CategoryEntity::class, $skill->id);
        $loadedTag->setCategory($loadedSkill);
        $this->em->flush();
        $this->em->clear();

        self::assertSame((string) $skill->id, (string) self::findOrFail($this->em, TagEntity::class, $tag->id)->category->id);
        self::assertCount(1, self::findOrFail($this->em, CategoryEntity::class, $area->id)->getTags());
        self::assertCount(1, self::findOrFail($this->em, CategoryEntity::class, $skill->id)->getTags());
    }

    public function testMovingATagUpdatesBothCategories(): void
    {
        $area = new CategoryEntity($this->user, 'Area');
        $skill = new CategoryEntity($this->user, 'Skill');
        $tag = $area->addTag('php');

        $tag->setCategory($skill);

        self::assertSame($skill, $tag->category);
        self::assertFalse($area->getTags()->contains($tag));
        self::assertTrue($skill->getTags()->contains($tag));
    }
}
