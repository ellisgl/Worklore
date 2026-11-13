<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Company\CompanyEntity;
use App\Company\CompanyRepository;
use App\Company\PositionEntity;
use App\Company\PositionRepository;
use App\Company\ProductEntity;
use App\Company\ProductRepository;
use App\Company\ProjectEntity;
use App\Company\ProjectRepository;
use App\Identity\User;
use App\Journal\JournalEntryEntity;
use App\Journal\JournalEntryRepository;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Taxonomy\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The list methods: ordering, scoping to the user and parent, and archived
 * parents hiding their children.
 */
final class ListMethodsTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->user = $this->persist(new User('me@example.com'));
    }

    // ---- companies --------------------------------------------------------

    public function testCompaniesSortedByNameAndScopedToUser(): void
    {
        $this->persist(new CompanyEntity($this->user, 'Globex'));
        $this->persist(new CompanyEntity($this->user, 'Acme'));
        $this->persist((new CompanyEntity($this->user, 'Initech'))->archive(new \DateTimeImmutable()));
        $this->persist(new CompanyEntity($this->persist(new User('other@example.com')), 'Theirs'));
        $this->em->flush();

        $repo = $this->repo(CompanyRepository::class);
        self::assertSame(['Acme', 'Globex'], $this->names($repo->getAllCompaniesByUser($this->user)));
        self::assertSame(['Acme', 'Globex', 'Initech'], $this->names($repo->getAllCompaniesByUser($this->user, includeArchived: true)));
    }

    // ---- products ---------------------------------------------------------

    public function testProductsSortedByNameAndScopedToCompany(): void
    {
        $acme = $this->persist(new CompanyEntity($this->user, 'Acme'));
        $globex = $this->persist(new CompanyEntity($this->user, 'Globex'));
        $this->persist(new ProductEntity($acme, $this->user, 'Widget'));
        $this->persist(new ProductEntity($acme, $this->user, 'Gadget'));
        $this->persist((new ProductEntity($acme, $this->user, 'Retired'))->archive(new \DateTimeImmutable()));
        $this->persist(new ProductEntity($globex, $this->user, 'Other'));
        $this->em->flush();

        $repo = $this->repo(ProductRepository::class);
        self::assertSame(['Gadget', 'Widget'], $this->names($repo->getAllProductsByCompany($this->user, $acme)));
        self::assertSame(['Gadget', 'Retired', 'Widget'], $this->names($repo->getAllProductsByCompany($this->user, $acme, includeArchived: true)));
    }

    public function testArchivedCompanyHidesItsProducts(): void
    {
        $acme = $this->persist((new CompanyEntity($this->user, 'Acme'))->archive(new \DateTimeImmutable()));
        $this->persist(new ProductEntity($acme, $this->user, 'Widget'));
        $this->em->flush();

        $repo = $this->repo(ProductRepository::class);
        self::assertSame([], $repo->getAllProductsByCompany($this->user, $acme));
        self::assertSame(['Widget'], $this->names($repo->getAllProductsByCompany($this->user, $acme, includeArchived: true)));
    }

    // ---- projects ---------------------------------------------------------

    public function testProjectsSortedByNameAndScopedToProduct(): void
    {
        $widget = $this->product();
        $gadget = $this->persist(new ProductEntity($widget->company, $this->user, 'Gadget'));
        $this->persist(new ProjectEntity($widget, $this->user, 'Launch'));
        $this->persist(new ProjectEntity($widget, $this->user, 'Beta'));
        $this->persist(new ProjectEntity($gadget, $this->user, 'Other'));
        $this->em->flush();

        self::assertSame(['Beta', 'Launch'], $this->names($this->repo(ProjectRepository::class)->getAllProjectsByProduct($this->user, $widget)));
    }

    public function testArchivedProductOrCompanyHidesItsProjects(): void
    {
        $widget = $this->product();
        $this->persist(new ProjectEntity($widget, $this->user, 'Launch'));
        $this->em->flush();
        $repo = $this->repo(ProjectRepository::class);

        $widget->archive(new \DateTimeImmutable());
        $this->em->flush();
        self::assertSame([], $repo->getAllProjectsByProduct($this->user, $widget), 'Archived product');

        $widget->unarchive();
        $widget->company->archive(new \DateTimeImmutable());
        $this->em->flush();
        self::assertSame([], $repo->getAllProjectsByProduct($this->user, $widget), 'Archived company');

        self::assertSame(['Launch'], $this->names($repo->getAllProjectsByProduct($this->user, $widget, includeArchived: true)));
    }

    // ---- journal entries --------------------------------------------------

    public function testJournalEntriesNewestFirst(): void
    {
        $project = $this->project();
        $this->persist(new JournalEntryEntity($project, $this->user, 'First', 'Body', new \DateTimeImmutable('2024-01-01 09:00')));
        $this->persist(new JournalEntryEntity($project, $this->user, 'Third', 'Body', new \DateTimeImmutable('2024-03-01 09:00')));
        $this->persist(new JournalEntryEntity($project, $this->user, 'Second', 'Body', new \DateTimeImmutable('2024-02-01 09:00')));
        $this->em->flush();

        $titles = array_map(static fn (JournalEntryEntity $j) => $j->title->value, $this->repo(JournalEntryRepository::class)->getAllEntriesByProject($this->user, $project));

        self::assertSame(['Third', 'Second', 'First'], $titles);
    }

    public function testArchivedAncestorHidesJournalEntries(): void
    {
        $project = $this->project();
        $this->persist(new JournalEntryEntity($project, $this->user, 'Kickoff', 'Body'));
        $this->em->flush();
        $repo = $this->repo(JournalEntryRepository::class);

        foreach (['project' => $project, 'product' => $project->product, 'company' => $project->product->company] as $level => $ancestor) {
            $ancestor->archive(new \DateTimeImmutable());
            $this->em->flush();
            self::assertSame([], $repo->getAllEntriesByProject($this->user, $project), "Archived $level");
            self::assertCount(1, $repo->getAllEntriesByProject($this->user, $project, includeArchived: true), "Archived $level, includeArchived");
            $ancestor->unarchive();
            $this->em->flush();
        }

        self::assertCount(1, $repo->getAllEntriesByProject($this->user, $project), 'Nothing archived');
    }

    public function testJournalEntryFindOneByIdForUser(): void
    {
        $entry = $this->persist(new JournalEntryEntity($this->project(), $this->user, 'Kickoff', 'Body'));
        $this->em->flush();
        $repo = $this->repo(JournalEntryRepository::class);

        self::assertSame($entry, $repo->findOneByIdForUser($entry->id, $this->user));
        self::assertNull($repo->findOneByIdForUser($entry->id, $this->persist(new User('other@example.com'))));
    }

    // ---- positions --------------------------------------------------------

    public function testArchivedCompanyHidesItsPositions(): void
    {
        $acme = $this->persist(new CompanyEntity($this->user, 'Acme'));
        $this->persist(new PositionEntity($acme, $this->user, 'Dev', new \DateTimeImmutable('2024-01-01')));
        $acme->archive(new \DateTimeImmutable());
        $this->em->flush();

        $repo = $this->repo(PositionRepository::class);
        self::assertSame([], $repo->getAllPositionsByCompany($this->user, $acme));
        self::assertCount(1, $repo->getAllPositionsByCompany($this->user, $acme, includeArchived: true));
    }

    public function testCurrentPositionsAcrossCompanies(): void
    {
        $acme = $this->persist(new CompanyEntity($this->user, 'Acme'));
        $globex = $this->persist(new CompanyEntity($this->user, 'Globex'));
        $gone = $this->persist((new CompanyEntity($this->user, 'Gone'))->archive(new \DateTimeImmutable()));
        $this->persist(new PositionEntity($acme, $this->user, 'Consultant', new \DateTimeImmutable('2023-01-01')));
        $this->persist(new PositionEntity($globex, $this->user, 'Lead', new \DateTimeImmutable('2024-01-01')));
        $this->persist((new PositionEntity($acme, $this->user, 'Junior', new \DateTimeImmutable('2020-01-01')))->end(new \DateTimeImmutable('2022-12-31')));
        $this->persist((new PositionEntity($acme, $this->user, 'Hidden', new \DateTimeImmutable('2021-01-01')))->archive(new \DateTimeImmutable()));
        $this->persist(new PositionEntity($gone, $this->user, 'At archived company', new \DateTimeImmutable('2022-01-01')));
        $this->em->flush();
        $this->em->clear();

        $current = $this->repo(PositionRepository::class)->getCurrentPositionsByUser($this->em->find(User::class, $this->user->id));

        self::assertSame(['Lead', 'Consultant'], array_map(static fn (PositionEntity $p) => $p->title->value, $current));
        self::assertFalse($this->em->getUnitOfWork()->isUninitializedObject($current[0]->company), 'Company is fetched in the same query');
    }

    // ---- tags -------------------------------------------------------------

    public function testTagsByCategory(): void
    {
        $area = $this->persist(new CategoryEntity($this->user, 'Area'));
        $skill = $this->persist(new CategoryEntity($this->user, 'Skill'));
        $this->persist(new TagEntity($area, $this->user, 'frontend'));
        $this->persist(new TagEntity($area, $this->user, 'backend'));
        $this->persist((new TagEntity($area, $this->user, 'old'))->archive(new \DateTimeImmutable()));
        $this->persist(new TagEntity($skill, $this->user, 'php'));
        $this->em->flush();

        $repo = $this->repo(TagRepository::class);
        self::assertSame(['backend', 'frontend'], $this->names($repo->getAllTagsByCategory($this->user, $area)));
        self::assertSame(['backend', 'frontend', 'old'], $this->names($repo->getAllTagsByCategory($this->user, $area, includeArchived: true)));

        $area->archive(new \DateTimeImmutable());
        $this->em->flush();
        self::assertSame([], $repo->getAllTagsByCategory($this->user, $area), 'Archived category hides its tags');
    }

    public function testSameNamesSortInCreationOrder(): void
    {
        // Category names aren't unique; the id tie-breaker (UUIDv7, time
        // ordered) keeps each category's tags together, oldest category first.
        $first = $this->persist(new CategoryEntity($this->user, 'Area'));
        $second = $this->persist(new CategoryEntity($this->user, 'Area'));
        $this->persist(new TagEntity($second, $this->user, 'a-second'));
        $this->persist(new TagEntity($first, $this->user, 'z-first'));
        $this->persist(new TagEntity($first, $this->user, 'b-first'));
        $this->em->flush();

        $tags = $this->repo(TagRepository::class)->getAllTagsByUser($this->user);

        self::assertSame(['b-first', 'z-first', 'a-second'], $this->names($tags));
    }

    // ---- helpers ----------------------------------------------------------

    private function product(): ProductEntity
    {
        return $this->persist(new ProductEntity($this->persist(new CompanyEntity($this->user, 'Acme')), $this->user, 'Widget'));
    }

    private function project(): ProjectEntity
    {
        return $this->persist(new ProjectEntity($this->product(), $this->user, 'Launch'));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function repo(string $class): object
    {
        return self::getContainer()->get($class);
    }

    /**
     * @param list<object> $records
     *
     * @return list<string>
     */
    private function names(array $records): array
    {
        return array_map(static fn (object $r) => $r->name->value, $records);
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
