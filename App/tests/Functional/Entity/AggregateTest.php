<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Company\CompanyRepository;
use App\Company\ProductEntity;
use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\CategoryRepository;
use App\Tests\Functional\FindsEntities;
use DateTimeImmutable;
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
    public function testPersistingTheCompanySavesEverythingInside(): void
    {
        $company = new CompanyEntity($this->user, 'Acme');
        $widget = $company->addProduct('Widget');
        $widget->addProject('Launch');
        $widget->addProject('Beta');
        $company->addPosition('Developer', new DateTimeImmutable('2024-01-01'));

        $this->em->persist($company);   // only the root
        $this->em->flush();
        $this->em->clear();

        $companies = self::getContainer()->get(CompanyRepository::class);
        $user = self::findOrFail($this->em, User::class, $this->user->id);
        $loaded = $companies->findOneByIdForOwner($company->id, $user);
        self::assertNotNull($loaded);
        self::assertCount(1, $loaded->getProducts());
        $product = $loaded->getProducts()->first();
        self::assertInstanceOf(ProductEntity::class, $product);
        self::assertCount(2, $product->getProjects());
        self::assertCount(1, $loaded->getPositions());
        self::assertNotNull($companies->findProductByIdForOwner($widget->id, $user));
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

        $user = self::findOrFail($this->em, User::class, $this->user->id);
        self::assertCount(2, self::getContainer()->get(CategoryRepository::class)->getAllTagsByCategory($user, $category));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testChildrenAddedLaterAreSavedOnFlush(): void
    {
        $company = new CompanyEntity($this->user, 'Acme');
        $this->em->persist($company);
        $this->em->flush();

        $company->addProduct('Widget');     // no persist() call
        $this->em->flush();
        $this->em->clear();

        $user = self::findOrFail($this->em, User::class, $this->user->id);
        self::assertCount(1, self::getContainer()->get(CompanyRepository::class)->getAllProductsByCompany($user, $company));
    }

    public function testFactoriesKeepTheParentsListsInSync(): void
    {
        $company = new CompanyEntity($this->user, 'Acme');
        $product = $company->addProduct('Widget');
        $project = $product->addProject('Launch');
        $position = $company->addPosition('Dev', new DateTimeImmutable('2024-01-01'));
        $category = new CategoryEntity($this->user, 'Area');
        $tag = $category->addTag('php');

        self::assertSame($company, $product->company);
        self::assertTrue($company->getProducts()->contains($product));
        self::assertTrue($product->getProjects()->contains($project));
        self::assertTrue($company->getPositions()->contains($position));
        self::assertTrue($category->getTags()->contains($tag));
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
