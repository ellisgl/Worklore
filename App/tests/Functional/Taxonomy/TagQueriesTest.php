<?php

declare(strict_types=1);

namespace App\Tests\Functional\Taxonomy;

use App\Identity\User;
use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use App\Taxonomy\Query\TagQueries;
use App\Taxonomy\Query\TagSummary;
use App\Taxonomy\TagId;
use App\Tests\Functional\CreatesRecords;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

use const DATE_RFC3339;

final class TagQueriesTest extends KernelTestCase
{
    use CreatesRecords;

    private TagQueries $queries;
    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->queries = self::getContainer()->get(TagQueries::class);
        $this->me = $this->owner();
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testAllTagsAreSortedByCategoryThenName(): void
    {
        // Created out of order on purpose
        $skill = $this->category($this->me, 'skill');
        $area = $this->category($this->me, 'area');
        $this->tag($skill, 'sql');
        $this->tag($area, 'frontend');
        $this->tag($skill, 'php');
        $this->tag($area, 'backend');

        self::assertSame(
            ['area/backend', 'area/frontend', 'skill/php', 'skill/sql'],
            $this->labels($this->queries->listForOwner($this->me->id)->items),
        );
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedTagsAndTagsInArchivedCategoriesAreLeftOutUnlessAsked(): void
    {
        $area = $this->category($this->me, 'area');
        $old = $this->category($this->me, 'old', archived: true);
        $this->tag($area, 'active');
        $this->tag($area, 'archived', archived: true);
        $this->tag($old, 'in-archived');

        self::assertSame(['area/active'], $this->labels($this->queries->listForOwner($this->me->id)->items));
        self::assertSame(
            ['area/active', 'area/archived', 'old/in-archived'],
            $this->labels($this->queries->listForOwner($this->me->id, includeArchived: true)->items),
        );
        self::assertSame([], $this->queries->listForCategory($this->me->id, $old->id())->items);
        self::assertSame(['in-archived'], $this->names($this->queries->listForCategory($this->me->id, $old->id(), includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testCategoryListIsSortedByNameAndScoped(): void
    {
        $area = $this->category($this->me, 'area');
        $skill = $this->category($this->me, 'skill');
        $this->tag($area, 'frontend');
        $this->tag($area, 'backend');
        $this->tag($skill, 'php');

        self::assertSame(['backend', 'frontend'], $this->names($this->queries->listForCategory($this->me->id, $area->id())->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    public function testListsArePaged(): void
    {
        $area = $this->category($this->me, 'area');
        foreach (['c', 'a', 'b'] as $name) {
            $this->tag($area, $name);
        }

        $page = $this->queries->listForCategory($this->me->id, $area->id(), page: new Page(2, 2));

        self::assertSame(['c'], $this->names($page->items));
        self::assertSame(3, $page->total);
        self::assertSame(3, $this->queries->listForOwner($this->me->id, page: new Page(1, 1))->total);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $area = $this->category($this->me, 'Area');
        $tag = $this->tag($area, 'backend', '2024-01-02 03:04:05 UTC');

        $summary = $this->queries->findForOwner($this->me->id, $tag->id());

        self::assertNotNull($summary);
        self::assertSame((string) $tag->id(), $summary->id);
        self::assertSame((string) $area->id(), $summary->categoryId);
        self::assertSame('Area', $summary->categoryName);
        self::assertSame('backend', $summary->name);
        self::assertSame('2024-01-02T03:04:05+00:00', $summary->createdAt->format(DATE_RFC3339));
        self::assertNull($summary->archivedAt);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testOtherOwnersTagsAreInvisible(): void
    {
        $theirs = $this->category($this->owner('other@example.com'), 'theirs');
        $tag = $this->tag($theirs, 'theirs');
        $this->tag($this->category($this->me, 'mine'), 'mine');

        self::assertSame(['mine'], $this->names($this->queries->listForOwner($this->me->id)->items));
        self::assertSame([], $this->queries->listForCategory($this->me->id, $theirs->id())->items);
        self::assertNull($this->queries->findForOwner($this->me->id, $tag->id()));
        self::assertNull($this->queries->findForOwner($this->me->id, TagId::generate()));
    }

    /**
     * @param list<TagSummary> $summaries
     *
     * @return list<string>
     */
    private function names(array $summaries): array
    {
        return array_map(static fn (TagSummary $s) => $s->name, $summaries);
    }

    /**
     * @param list<TagSummary> $summaries
     *
     * @return list<string> "category/tag"
     */
    private function labels(array $summaries): array
    {
        return array_map(static fn (TagSummary $s) => $s->categoryName.'/'.$s->name, $summaries);
    }
}
