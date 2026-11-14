<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\CategoryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The pre-save checks behind uniq_company_user_name and uniq_tag_category_name.
 */
final class NameExistsTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CategoryRepository $tags;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->tags = self::getContainer()->get(CategoryRepository::class);
    }

    public function testTagNameCheckIgnoresCaseLikeTheConstraint(): void
    {
        [, $area] = $this->userWithCategory();
        $this->persist($area->addTag('PHP'));
        $this->em->flush();

        self::assertTrue($this->tags->nameExistsInCategory($area, 'php'));
    }

    // ---- CategoryRepository::nameExistsForOwner() ---------------------------

    public function testCategoryNameCheck(): void
    {
        $me = $this->persist(new User('a@example.com'));
        $other = $this->persist(new User('b@example.com'));
        $skill = $this->persist(new CategoryEntity($me, 'Skill'));
        $this->persist(new CategoryEntity($me, 'Old')->archive(new DateTimeImmutable()));
        $this->em->flush();

        self::assertTrue($this->tags->nameExistsForOwner($me, ' SKILL '), 'Trimmed, any case');
        self::assertTrue($this->tags->nameExistsForOwner($me, 'old'), 'Archived ones hold their name');
        self::assertFalse($this->tags->nameExistsForOwner($other, 'Skill'), 'Free for another user');
        self::assertFalse($this->tags->nameExistsForOwner($me, 'skill', except: $skill), 'Its own name');
        self::assertTrue($this->tags->nameExistsForOwner($me, 'old', except: $skill));
    }

    // ---- CategoryRepository::nameExistsInCategory() ----------------------------

    public function testTagNameTakenInCategory(): void
    {
        [, $area] = $this->userWithCategory();
        $this->persist($area->addTag('backend'));
        $this->em->flush();

        self::assertTrue($this->tags->nameExistsInCategory($area, 'backend'));
        self::assertTrue($this->tags->nameExistsInCategory($area, ' backend '), 'Input is trimmed like the stored name');
        self::assertFalse($this->tags->nameExistsInCategory($area, 'frontend'));
    }

    public function testTagNameInAnotherCategoryIsFree(): void
    {
        [$user, $area] = $this->userWithCategory();
        $skill = $this->persist(new CategoryEntity($user, 'Skill'));
        $this->persist($skill->addTag('backend'));
        $this->em->flush();

        self::assertFalse($this->tags->nameExistsInCategory($area, 'backend'));
    }

    public function testArchivedTagStillHoldsItsName(): void
    {
        [, $area] = $this->userWithCategory();
        $this->persist($area->addTag('backend')->archive(new DateTimeImmutable()));
        $this->em->flush();

        self::assertTrue($this->tags->nameExistsInCategory($area, 'backend'));
    }

    public function testTagCanKeepItsOwnNameWhenExcluded(): void
    {
        [, $area] = $this->userWithCategory();
        $backend = $this->persist($area->addTag('backend'));
        $this->persist($area->addTag('frontend'));
        $this->em->flush();

        self::assertFalse($this->tags->nameExistsInCategory($area, 'backend', except: $backend));
        self::assertTrue($this->tags->nameExistsInCategory($area, 'frontend', except: $backend));
    }

    /**
     * @return array{User, CategoryEntity}
     */
    private function userWithCategory(): array
    {
        $user = $this->persist(new User('a@example.com'));

        return [$user, $this->persist(new CategoryEntity($user, 'Area'))];
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
