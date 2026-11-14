<?php

declare(strict_types=1);

namespace App\Tests\Functional\Taxonomy;

use App\Identity\User;
use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use App\Taxonomy\CategoryId;
use App\Taxonomy\Query\CategoryQueries;
use App\Taxonomy\Query\CategorySummary;
use App\Tests\Functional\CreatesRecords;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

use const DATE_RFC3339;

final class CategoryQueriesTest extends KernelTestCase
{
    use CreatesRecords;

    private CategoryQueries $queries;
    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->queries = self::getContainer()->get(CategoryQueries::class);
        $this->me = $this->owner();
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsSortedByNameAndScopedToTheOwner(): void
    {
        // Created out of order on purpose
        $this->category($this->me, 'skill');
        $this->category($this->me, 'area');
        $this->category($this->me, 'location');
        $this->category($this->owner('other@example.com'), 'theirs');

        self::assertSame(['area', 'location', 'skill'], $this->names($this->queries->listForOwner($this->me->id)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedCategoriesAreLeftOutUnlessAsked(): void
    {
        $this->category($this->me, 'active');
        $this->category($this->me, 'archived', archived: true);

        self::assertSame(['active'], $this->names($this->queries->listForOwner($this->me->id)->items));
        self::assertSame(['active', 'archived'], $this->names($this->queries->listForOwner($this->me->id, includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $area = $this->category($this->me, 'Area', 'Where I work', '2024-01-02 03:04:05 UTC');
        $this->tag($area, 'backend');
        $this->tag($area, 'frontend');
        $this->tag($area, 'old', archived: true);

        $summary = $this->queries->findForOwner($this->me->id, $area->id());

        self::assertNotNull($summary);
        self::assertSame((string) $area->id(), $summary->id);
        self::assertSame('Area', $summary->name);
        self::assertSame('Where I work', $summary->description);
        self::assertSame('2024-01-02T03:04:05+00:00', $summary->createdAt->format(DATE_RFC3339));
        self::assertNull($summary->archivedAt);
        self::assertSame(2, $summary->tagCount, 'Active tags only');
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    public function testListIsPaged(): void
    {
        foreach (['c', 'a', 'b'] as $name) {
            $this->category($this->me, $name);
        }

        $page = $this->queries->listForOwner($this->me->id, page: new Page(2, 2));

        self::assertSame(['c'], $this->names($page->items));
        self::assertSame(3, $page->total);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testFindAndExistsIgnoreOtherOwnersAndUnknownIds(): void
    {
        $theirs = $this->category($this->owner('other@example.com'), 'theirs');
        $mine = $this->category($this->me, 'mine', archived: true);

        self::assertNull($this->queries->findForOwner($this->me->id, $theirs->id()));
        self::assertNull($this->queries->findForOwner($this->me->id, CategoryId::generate()));
        self::assertNotNull($this->queries->findForOwner($this->me->id, $mine->id()), 'Archived ones too');
        self::assertFalse($this->queries->existsForOwner($this->me->id, $theirs->id()));
        self::assertTrue($this->queries->existsForOwner($this->me->id, $mine->id()));
    }

    /**
     * @param list<CategorySummary> $summaries
     *
     * @return list<string>
     */
    private function names(array $summaries): array
    {
        return array_map(static fn (CategorySummary $s) => $s->name, $summaries);
    }
}
