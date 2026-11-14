<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\CompanyId;
use App\Company\Query\CompanyQueries;
use App\Company\Query\CompanySummary;
use App\Identity\User;
use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use App\Tests\Functional\CreatesCompanyRecords;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

use const DATE_RFC3339;

final class CompanyQueriesTest extends KernelTestCase
{
    use CreatesCompanyRecords;

    private CompanyQueries $queries;
    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->queries = self::getContainer()->get(CompanyQueries::class);
        $this->me = $this->owner();
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsSortedByNameAndScopedToTheOwner(): void
    {
        $this->company($this->me, 'Globex');
        $this->company($this->me, 'Acme');
        $this->company($this->me, 'Initech', archived: true);
        $this->company($this->owner('other@example.com'), 'Theirs');

        self::assertSame(['Acme', 'Globex'], $this->names($this->queries->listForOwner($this->me->id)->items));
        self::assertSame(['Acme', 'Globex', 'Initech'], $this->names($this->queries->listForOwner($this->me->id, includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $acme = $this->company($this->me, 'Acme', 'Widgets', '2024-01-02 03:04:05 UTC');
        $this->product($acme);
        $this->position($acme, 'Old', '2020-01-01', '2021-01-01');
        $this->position($acme, 'Lead', '2022-01-01');
        $this->position($acme, 'Hidden', '2023-01-01', archived: true);

        [$summary] = $this->queries->listForOwner($this->me->id)->items;

        self::assertSame((string) $acme->id(), $summary->id);
        self::assertSame('Widgets', $summary->overview);
        self::assertSame('2024-01-02T03:04:05+00:00', $summary->createdAt->format(DATE_RFC3339));
        self::assertNull($summary->archivedAt);
        self::assertSame(1, $summary->productCount);
        self::assertSame('Lead', $summary->currentPosition, 'Newest current, non-archived position');
    }

    /**
     * @throws Exception
     */
    public function testOwnerWithoutCompaniesGetsEmptyList(): void
    {
        self::assertSame([], $this->queries->listForOwner($this->me->id)->items);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testFindReturnsOwnCompanyIncludingArchived(): void
    {
        $acme = $this->company($this->me, 'Acme');
        $gone = $this->company($this->me, 'Gone', archived: true);

        self::assertSame('Acme', $this->queries->findForOwner($this->me->id, $acme->id())?->name);
        self::assertNotNull($this->queries->findForOwner($this->me->id, $gone->id())?->archivedAt);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testFindIgnoresOtherOwnersAndUnknownIds(): void
    {
        $theirs = $this->company($this->owner('other@example.com'), 'Theirs');

        self::assertNull($this->queries->findForOwner($this->me->id, $theirs->id()));
        self::assertNull($this->queries->findForOwner($this->me->id, CompanyId::generate()));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    public function testListIsPaged(): void
    {
        foreach (['E', 'C', 'A', 'D', 'B'] as $name) {
            $this->company($this->me, $name);
        }
        $this->company($this->me, 'Archived', archived: true);

        $second = $this->queries->listForOwner($this->me->id, page: new Page(2, 2));
        $last = $this->queries->listForOwner($this->me->id, page: new Page(3, 2));
        $beyond = $this->queries->listForOwner($this->me->id, page: new Page(4, 2));

        self::assertSame(['C', 'D'], $this->names($second->items));
        self::assertSame(2, $second->page);
        self::assertSame(2, $second->perPage);
        self::assertSame(5, $second->total, 'Archived left out of the total too');
        self::assertSame(['E'], $this->names($last->items));
        self::assertSame([], $beyond->items);
        self::assertSame(5, $beyond->total);
        self::assertSame(6, $this->queries->listForOwner($this->me->id, includeArchived: true)->total);
    }

    /**
     * @param list<CompanySummary> $summaries
     *
     * @return list<string>
     */
    private function names(array $summaries): array
    {
        return array_map(static fn (CompanySummary $s) => $s->name, $summaries);
    }
}
