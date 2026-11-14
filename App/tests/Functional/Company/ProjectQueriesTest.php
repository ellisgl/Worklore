<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\Product;
use App\Company\ProjectId;
use App\Company\Query\ProjectQueries;
use App\Company\Query\ProjectSummary;
use App\Identity\User;
use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use App\Taxonomy\Query\TagRef;
use App\Taxonomy\TagId;
use App\Tests\Functional\CreatesRecords;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

use const DATE_RFC3339;

final class ProjectQueriesTest extends KernelTestCase
{
    use CreatesRecords;

    private ProjectQueries $queries;
    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->queries = self::getContainer()->get(ProjectQueries::class);
        $this->me = $this->owner();
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsSortedByNameAndScopedToTheProduct(): void
    {
        $widget = $this->widget();
        $gadget = $this->product($this->company($this->me, 'Globex'), 'Gadget');
        $this->project($widget, 'Launch');
        $this->project($widget, 'Beta');
        $this->project($widget, 'Old', archived: true);
        $this->project($gadget, 'Other');

        self::assertSame(['Beta', 'Launch'], $this->names($this->queries->listForProduct($this->me->id, $widget->id())->items));
        self::assertSame(['Beta', 'Launch', 'Old'], $this->names($this->queries->listForProduct($this->me->id, $widget->id(), includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedProductOrCompanyHidesItsProjects(): void
    {
        $acme = $this->company($this->me);
        $widget = $this->product($acme);
        $this->project($widget, 'Launch');

        $widget->archive(new DateTimeImmutable());
        $this->saved($widget);
        self::assertSame([], $this->queries->listForProduct($this->me->id, $widget->id())->items, 'Archived product');

        $widget->unarchive();
        $acme->archive(new DateTimeImmutable());
        $this->saved($widget);
        $this->saved($acme);
        self::assertSame([], $this->queries->listForProduct($this->me->id, $widget->id())->items, 'Archived company');

        self::assertSame(['Launch'], $this->names($this->queries->listForProduct($this->me->id, $widget->id(), includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $widget = $this->widget();
        $launch = $this->project($widget, 'Launch', overview: 'Go live', at: '2024-01-02 03:04:05 UTC');

        $summary = $this->queries->findForOwner($this->me->id, $launch->id());

        self::assertNotNull($summary);
        self::assertSame((string) $launch->id(), $summary->id);
        self::assertSame((string) $widget->id(), $summary->productId);
        self::assertSame((string) $widget->companyId, $summary->companyId);
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
        $theirs = $this->project($this->product($this->company($this->owner('other@example.com'), 'Theirs'), 'Theirs'), 'Theirs');

        self::assertNull($this->queries->findForOwner($this->me->id, $theirs->id()));
        self::assertNull($this->queries->findForOwner($this->me->id, ProjectId::generate()));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testProjectsCarryTheirTags(): void
    {
        $widget = $this->widget();
        [$php, $backend] = $this->tags(['Skill', 'php'], ['Area', 'backend']);
        $launch = $this->project($widget, 'Launch', [$php, $backend]);
        $this->project($widget, 'Beta');

        $found = $this->queries->findForOwner($this->me->id, $launch->id());
        $listed = $this->queries->listForProduct($this->me->id, $widget->id())->items;

        self::assertNotNull($found);
        self::assertSame(['Area/backend', 'Skill/php'], self::tagLabels($found), 'By category, then name');
        self::assertSame([[], ['Area/backend', 'Skill/php']], array_map(self::tagLabels(...), $listed), 'Beta, then Launch');
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    public function testFilterByTag(): void
    {
        $widget = $this->widget();
        [$backend, $frontend] = $this->tags(['Area', 'backend'], ['Area', 'frontend']);
        $this->project($widget, 'Both', [$backend, $frontend]);
        $this->project($widget, 'Backend', [$backend]);
        $this->project($widget, 'Untagged');
        $this->project($widget, 'Old backend', [$backend], archived: true);

        $byBackend = $this->queries->listForProduct($this->me->id, $widget->id(), tagId: $backend);

        self::assertSame(['Backend', 'Both'], $this->names($byBackend->items));
        self::assertSame(2, $byBackend->total);
        self::assertSame(['Backend', 'Both', 'Old backend'], $this->names($this->queries->listForProduct($this->me->id, $widget->id(), includeArchived: true, tagId: $backend)->items));
        self::assertSame(['Both'], $this->names($this->queries->listForProduct($this->me->id, $widget->id(), page: new Page(2, 1), tagId: $backend)->items));
        self::assertSame([], $this->queries->listForProduct($this->me->id, $widget->id(), tagId: TagId::generate())->items);
    }

    /**
     * @throws DateMalformedStringException
     */
    private function widget(): Product
    {
        return $this->product($this->company($this->me));
    }

    /**
     * Creates the tags (and their categories, once each); returns the tag
     * ids in order.
     *
     * @param array{string, string} ...$categoryAndTag
     *
     * @return list<TagId>
     *
     * @throws DateMalformedStringException
     */
    private function tags(array ...$categoryAndTag): array
    {
        $categories = [];
        $ids = [];
        foreach ($categoryAndTag as [$category, $tag]) {
            $categories[$category] ??= $this->category($this->me, $category);
            $ids[] = $this->tag($categories[$category], $tag)->id();
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private static function tagLabels(ProjectSummary $summary): array
    {
        return array_map(static fn (TagRef $t) => $t->categoryName.'/'.$t->name, $summary->tags);
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
