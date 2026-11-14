<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\ProductId;
use App\Company\Query\ProductQueries;
use App\Company\Query\ProductSummary;
use App\Identity\User;
use App\Tests\Functional\CreatesRecords;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

use const DATE_RFC3339;

final class ProductQueriesTest extends KernelTestCase
{
    use CreatesRecords;

    private ProductQueries $queries;
    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->queries = self::getContainer()->get(ProductQueries::class);
        $this->me = $this->owner();
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsSortedByNameAndScopedToTheCompany(): void
    {
        $acme = $this->company($this->me, 'Acme');
        $globex = $this->company($this->me, 'Globex');
        $this->product($acme, 'Widget');
        $this->product($acme, 'Gadget');
        $this->product($acme, 'Retired', archived: true);
        $this->product($globex, 'Other');

        self::assertSame(['Gadget', 'Widget'], $this->names($this->queries->listForCompany($this->me->id, $acme->id())->items));
        self::assertSame(['Gadget', 'Retired', 'Widget'], $this->names($this->queries->listForCompany($this->me->id, $acme->id(), includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedCompanyHidesItsProducts(): void
    {
        $acme = $this->company($this->me, 'Acme', archived: true);
        $this->product($acme, 'Widget');

        self::assertSame([], $this->queries->listForCompany($this->me->id, $acme->id())->items);
        self::assertSame(['Widget'], $this->names($this->queries->listForCompany($this->me->id, $acme->id(), includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $acme = $this->company($this->me, 'Acme');
        $widget = $this->product($acme, 'Widget', 'Our widget', '2024-01-02 03:04:05 UTC');
        $this->project($widget, 'Launch');
        $this->project($widget, 'Old', archived: true);

        [$summary] = $this->queries->listForCompany($this->me->id, $acme->id())->items;

        self::assertSame((string) $widget->id(), $summary->id);
        self::assertSame((string) $acme->id(), $summary->companyId);
        self::assertSame('Our widget', $summary->overview);
        self::assertSame('2024-01-02T03:04:05+00:00', $summary->createdAt->format(DATE_RFC3339));
        self::assertNull($summary->archivedAt);
        self::assertSame(1, $summary->projectCount, 'Active projects only');
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testFindReturnsOwnProductIncludingArchived(): void
    {
        $widget = $this->product($this->company($this->me), archived: true);

        self::assertNotNull($this->queries->findForOwner($this->me->id, $widget->id())?->archivedAt);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testFindIgnoresOtherOwnersAndUnknownIds(): void
    {
        $theirs = $this->product($this->company($this->owner('other@example.com'), 'Theirs'), 'Theirs');

        self::assertNull($this->queries->findForOwner($this->me->id, $theirs->id()));
        self::assertNull($this->queries->findForOwner($this->me->id, ProductId::generate()));
    }

    /**
     * @param list<ProductSummary> $summaries
     *
     * @return list<string>
     */
    private function names(array $summaries): array
    {
        return array_map(static fn (ProductSummary $s) => $s->name, $summaries);
    }
}
