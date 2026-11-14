<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Company\Company;
use App\Company\CompanyId;
use App\Company\CompanyRepository;
use App\Company\Position;
use App\Company\PositionId;
use App\Company\PositionRepository;
use App\Company\Product;
use App\Company\ProductId;
use App\Company\ProductRepository;
use App\Company\Project;
use App\Company\ProjectId;
use App\Company\ProjectRepository;
use App\Identity\User;
use App\Taxonomy\Category;
use App\Taxonomy\CategoryId;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\Tag;
use App\Taxonomy\TagId;
use App\Taxonomy\TagRepository;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates records for tests the way the app does: through their aggregates
 * and repositories, so the events are stored and projected.
 * Needs a booted kernel.
 */
trait CreatesRecords
{
    /**
     * Saves a user, so records can belong to them.
     */
    protected function owner(string $email = 'me@example.com'): User
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = new User($email);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * @throws DateMalformedStringException
     */
    protected function company(User $owner, string $name = 'Acme', ?string $overview = null, string $at = 'now', bool $archived = false): Company
    {
        $company = Company::create(CompanyId::generate(), $owner->id, $name, $overview, new DateTimeImmutable($at));
        if ($archived) {
            $company->archive(new DateTimeImmutable());
        }

        return $this->saved($company);
    }

    /**
     * @throws DateMalformedStringException
     */
    protected function product(Company $company, string $name = 'Widget', ?string $overview = null, string $at = 'now', bool $archived = false): Product
    {
        $product = Product::create(ProductId::generate(), $company->id(), $company->ownerId(), $name, $overview, new DateTimeImmutable($at));
        if ($archived) {
            $product->archive(new DateTimeImmutable());
        }

        return $this->saved($product);
    }

    /**
     * @param list<TagId> $tagIds
     *
     * @throws DateMalformedStringException
     */
    protected function project(Product $product, string $name = 'Launch', array $tagIds = [], ?string $overview = null, string $at = 'now', bool $archived = false): Project
    {
        $project = Project::create(ProjectId::generate(), $product->id(), $product->ownerId(), $name, $overview, $tagIds, new DateTimeImmutable($at));
        if ($archived) {
            $project->archive(new DateTimeImmutable());
        }

        return $this->saved($project);
    }

    /**
     * @throws DateMalformedStringException
     */
    protected function position(
        Company $company,
        string $title = 'Developer',
        string $start = '2024-01-15',
        ?string $end = null,
        ?string $reasonLeft = null,
        ?string $overview = null,
        string $at = 'now',
        bool $archived = false,
    ): Position {
        $position = Position::record(
            PositionId::generate(),
            $company->id(),
            $company->ownerId(),
            $title,
            new DateTimeImmutable($start),
            $overview,
            null === $end ? null : new DateTimeImmutable($end),
            $reasonLeft,
            new DateTimeImmutable($at),
        );
        if ($archived) {
            $position->archive(new DateTimeImmutable());
        }

        return $this->saved($position);
    }

    /**
     * @throws DateMalformedStringException
     */
    protected function category(User $owner, string $name = 'Skill', ?string $description = null, string $at = 'now', bool $archived = false): Category
    {
        $category = Category::create(CategoryId::generate(), $owner->id, $name, $description, new DateTimeImmutable($at));
        if ($archived) {
            $category->archive(new DateTimeImmutable());
        }

        return $this->saved($category);
    }

    /**
     * @throws DateMalformedStringException
     */
    protected function tag(Category $category, string $name = 'php', string $at = 'now', bool $archived = false): Tag
    {
        $tag = Tag::create(TagId::generate(), $category->id(), $category->ownerId(), $name, new DateTimeImmutable($at));
        if ($archived) {
            $tag->archive(new DateTimeImmutable());
        }

        return $this->saved($tag);
    }

    /**
     * Saves the record's new events, e.g. after archive() in a test.
     *
     * @template T of Company|Product|Project|Position|Category|Tag
     *
     * @param T $record
     *
     * @return T
     */
    protected function saved(Company|Product|Project|Position|Category|Tag $record): Company|Product|Project|Position|Category|Tag
    {
        $container = self::getContainer();
        match (true) {
            $record instanceof Company => $container->get(CompanyRepository::class)->save($record),
            $record instanceof Product => $container->get(ProductRepository::class)->save($record),
            $record instanceof Project => $container->get(ProjectRepository::class)->save($record),
            $record instanceof Position => $container->get(PositionRepository::class)->save($record),
            $record instanceof Category => $container->get(CategoryRepository::class)->save($record),
            $record instanceof Tag => $container->get(TagRepository::class)->save($record),
        };

        return $record;
    }
}
