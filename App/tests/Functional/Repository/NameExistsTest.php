<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Company\CompanyEntity;
use App\Company\CompanyRepository;
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
    private CompanyRepository $companies;
    private CategoryRepository $tags;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
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
        $this->persist(new CompanyEntity($user, 'Acme')->archive(new DateTimeImmutable()));
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
        [, $area] = $this->userWithCategory();
        $this->persist($area->addTag('PHP'));
        $this->em->flush();

        self::assertTrue($this->tags->nameExistsInCategory($area, 'php'));
    }

    // ---- CompanyRepository::productNameExistsInCompany() --------------------

    public function testProductNameCheck(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        $globex = $this->persist(new CompanyEntity($user, 'Globex'));
        $widget = $acme->addProduct('Widget');
        $acme->addProduct('Gadget')->archive(new DateTimeImmutable());
        $this->em->flush();

        self::assertTrue($this->companies->productNameExistsInCompany($acme, ' WIDGET '), 'Trimmed, any case');
        self::assertTrue($this->companies->productNameExistsInCompany($acme, 'gadget'), 'Archived ones hold their name');
        self::assertFalse($this->companies->productNameExistsInCompany($globex, 'Widget'), 'Free in another company');
        self::assertFalse($this->companies->productNameExistsInCompany($acme, 'widget', except: $widget), 'Its own name');
        self::assertTrue($this->companies->productNameExistsInCompany($acme, 'gadget', except: $widget));
    }

    // ---- CompanyRepository::projectNameExistsInProduct() --------------------

    public function testProjectNameCheck(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        $widget = $acme->addProduct('Widget');
        $gadget = $acme->addProduct('Gadget');
        $launch = $widget->addProject('Launch');
        $widget->addProject('Beta')->archive(new DateTimeImmutable());
        $this->em->flush();

        self::assertTrue($this->companies->projectNameExistsInProduct($widget, ' LAUNCH '), 'Trimmed, any case');
        self::assertTrue($this->companies->projectNameExistsInProduct($widget, 'beta'), 'Archived ones hold their name');
        self::assertFalse($this->companies->projectNameExistsInProduct($gadget, 'Launch'), 'Free in another product');
        self::assertFalse($this->companies->projectNameExistsInProduct($widget, 'launch', except: $launch), 'Its own name');
        self::assertTrue($this->companies->projectNameExistsInProduct($widget, 'beta', except: $launch));
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
