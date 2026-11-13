<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\CompanyEntity;
use App\Company\ProductEntity;
use App\Company\ProjectId;
use App\Company\Query\ProjectQueries;
use App\Company\Query\ProjectSummary;
use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\Query\TagRef;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

final class ProjectQueriesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ProjectQueries $queries;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->queries = self::getContainer()->get(ProjectQueries::class);
        $this->owner = new User('me@example.com');
        $this->em->persist($this->owner);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsSortedByNameAndScopedToTheProduct(): void
    {
        $widget = $this->product();
        $gadget = $widget->company->addProduct('Gadget');
        $widget->addProject('Launch');
        $widget->addProject('Beta');
        $widget->addProject('Old')->archive(new DateTimeImmutable());
        $gadget->addProject('Other');
        $this->em->flush();

        self::assertSame(['Beta', 'Launch'], $this->names($this->queries->listForProduct($this->owner->id, $widget->id)->items));
        self::assertSame(['Beta', 'Launch', 'Old'], $this->names($this->queries->listForProduct($this->owner->id, $widget->id, includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedProductOrCompanyHidesItsProjects(): void
    {
        $widget = $this->product();
        $widget->addProject('Launch');
        $this->em->flush();

        $widget->archive(new DateTimeImmutable());
        $this->em->flush();
        self::assertSame([], $this->queries->listForProduct($this->owner->id, $widget->id)->items, 'Archived product');

        $widget->unarchive();
        $widget->company->archive(new DateTimeImmutable());
        $this->em->flush();
        self::assertSame([], $this->queries->listForProduct($this->owner->id, $widget->id)->items, 'Archived company');

        self::assertSame(['Launch'], $this->names($this->queries->listForProduct($this->owner->id, $widget->id, includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $widget = $this->product();
        $launch = $widget->addProject('Launch', 'Go live', new DateTimeImmutable('2024-01-02 03:04:05'));
        $this->em->flush();

        $summary = $this->queries->findForOwner($this->owner->id, $launch->id);

        self::assertNotNull($summary);
        self::assertSame((string) $launch->id, $summary->id);
        self::assertSame((string) $widget->id, $summary->productId);
        self::assertSame((string) $widget->company->id, $summary->companyId);
        self::assertSame('Go live', $summary->overview);
        self::assertSame('2024-01-02T03:04:05+00:00', $summary->createdAt->format(DATE_RFC3339));
        self::assertNull($summary->archivedAt);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testFindIgnoresOtherOwnersAndUnknownIds(): void
    {
        $other = new User('other@example.com');
        $this->em->persist($other);
        $theirs = new CompanyEntity($other, 'Theirs');
        $this->em->persist($theirs);
        $project = $theirs->addProduct('Theirs')->addProject('Theirs');
        $this->em->flush();

        self::assertNull($this->queries->findForOwner($this->owner->id, $project->id));
        self::assertNull($this->queries->findForOwner($this->owner->id, ProjectId::generate()));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testProjectsCarryTheirTags(): void
    {
        $widget = $this->product();
        $skill = new CategoryEntity($this->owner, 'Skill');
        $area = new CategoryEntity($this->owner, 'Area');
        $this->em->persist($skill);
        $this->em->persist($area);
        $launch = $widget->addProject('Launch')->setTags($skill->addTag('php'), $area->addTag('backend'));
        $widget->addProject('Beta');
        $this->em->flush();

        $found = $this->queries->findForOwner($this->owner->id, $launch->id);
        $listed = $this->queries->listForProduct($this->owner->id, $widget->id)->items;

        self::assertNotNull($found);
        self::assertSame(['Area/backend', 'Skill/php'], self::tagLabels($found), 'By category, then name');
        self::assertSame([[], ['Area/backend', 'Skill/php']], array_map(self::tagLabels(...), $listed), 'Beta, then Launch');
    }

    /**
     * @return list<string>
     */
    private static function tagLabels(ProjectSummary $summary): array
    {
        return array_map(static fn (TagRef $t) => $t->categoryName.'/'.$t->name, $summary->tags);
    }

    private function product(): ProductEntity
    {
        $company = new CompanyEntity($this->owner, 'Acme');
        $this->em->persist($company);

        return $company->addProduct('Widget');
    }

    /**
     * @param list<ProjectSummary> $summaries
     *
     * @return list<string>
     */
    private function names(array $summaries): array
    {
        return array_map(static fn (ProjectSummary $s) => $s->name, $summaries);
    }
}
