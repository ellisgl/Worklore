<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Company\CompanyEntity;
use App\Company\CompanyRepository;
use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Taxonomy\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The pre-save checks behind uniq_company_user_name and uniq_tag_category_name.
 */
final class NameExistsTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CompanyRepository $companies;
    private CategoryRepository $tags;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->companies = self::getContainer()->get(CompanyRepository::class);
        $this->tags = self::getContainer()->get(CategoryRepository::class);
    }

    // ---- CompanyRepository::nameExistsForOwner() ---------------------------

    public function testCompanyNameTakenByOwner(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $this->persist(new CompanyEntity($user, 'Acme'));
        $this->em->flush();

        self::assertTrue($this->companies->nameExistsForOwner($user, 'Acme'));
        self::assertTrue($this->companies->nameExistsForOwner($user, '  Acme  '), 'Input is trimmed like the stored name');
        self::assertFalse($this->companies->nameExistsForOwner($user, 'Globex'));
    }

    public function testCompanyNameOfAnotherUserIsFree(): void
    {
        $me = $this->persist(new User('me@example.com'));
        $other = $this->persist(new User('other@example.com'));
        $this->persist(new CompanyEntity($other, 'Acme'));
        $this->em->flush();

        self::assertFalse($this->companies->nameExistsForOwner($me, 'Acme'));
    }

    public function testArchivedCompanyStillHoldsItsName(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $this->persist((new CompanyEntity($user, 'Acme'))->archive(new \DateTimeImmutable()));
        $this->em->flush();

        self::assertTrue($this->companies->nameExistsForOwner($user, 'Acme'));
    }

    public function testCompanyCanKeepItsOwnNameWhenExcluded(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        $this->persist(new CompanyEntity($user, 'Globex'));
        $this->em->flush();

        self::assertFalse($this->companies->nameExistsForOwner($user, 'Acme', except: $acme), 'Saving under its current name');
        self::assertTrue($this->companies->nameExistsForOwner($user, 'Globex', except: $acme), 'Renaming onto another company');
    }

    public function testCompanyNameCheckIgnoresCaseLikeTheConstraint(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $this->persist(new CompanyEntity($user, 'Acme'));
        $this->em->flush();

        self::assertTrue($this->companies->nameExistsForOwner($user, 'acme'));
        self::assertTrue($this->companies->nameExistsForOwner($user, ' ACME '));
    }

    public function testTagNameCheckIgnoresCaseLikeTheConstraint(): void
    {
        [$user, $area] = $this->userWithCategory();
        $this->persist($area->addTag('PHP'));
        $this->em->flush();

        self::assertTrue($this->tags->nameExistsInCategory($area, 'php'));
    }

    // ---- CategoryRepository::nameExistsInCategory() ----------------------------

    public function testTagNameTakenInCategory(): void
    {
        [$user, $area] = $this->userWithCategory();
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
        [$user, $area] = $this->userWithCategory();
        $this->persist($area->addTag('backend')->archive(new \DateTimeImmutable()));
        $this->em->flush();

        self::assertTrue($this->tags->nameExistsInCategory($area, 'backend'));
    }

    public function testTagCanKeepItsOwnNameWhenExcluded(): void
    {
        [$user, $area] = $this->userWithCategory();
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
