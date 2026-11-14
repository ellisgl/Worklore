<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\PositionId;
use App\Company\Query\PositionQueries;
use App\Company\Query\PositionSummary;
use App\Identity\User;
use App\Tests\Functional\CreatesCompanyRecords;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

use const DATE_RFC3339;

final class PositionQueriesTest extends KernelTestCase
{
    use CreatesCompanyRecords;

    private PositionQueries $queries;
    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->queries = self::getContainer()->get(PositionQueries::class);
        $this->me = $this->owner();
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsNewestFirstAndScopedToTheCompany(): void
    {
        $acme = $this->company($this->me, 'Acme');
        $globex = $this->company($this->me, 'Globex');
        // Recorded out of order on purpose
        $this->position($acme, 'Senior Developer', '2022-06-01');
        $this->position($acme, 'Intern', '2018-06-01');
        $this->position($acme, 'Developer', '2019-03-01');
        $this->position($globex, 'Manager');

        self::assertSame(['Senior Developer', 'Developer', 'Intern'], $this->titles($this->queries->listForCompany($this->me->id, $acme->id())->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedPositionsAreLeftOutUnlessAsked(): void
    {
        $acme = $this->company($this->me, 'Acme');
        $this->position($acme, 'Developer', '2019-03-01');
        $this->position($acme, 'Archived', '2020-01-01', archived: true);

        self::assertSame(['Developer'], $this->titles($this->queries->listForCompany($this->me->id, $acme->id())->items));
        self::assertSame(['Archived', 'Developer'], $this->titles($this->queries->listForCompany($this->me->id, $acme->id(), includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedCompanyHidesItsPositions(): void
    {
        $acme = $this->company($this->me, 'Acme', archived: true);
        $this->position($acme, 'Developer');

        self::assertSame([], $this->queries->listForCompany($this->me->id, $acme->id())->items);
        self::assertSame(['Developer'], $this->titles($this->queries->listForCompany($this->me->id, $acme->id(), includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testAnotherOwnerGetsNothing(): void
    {
        $acme = $this->company($this->me, 'Acme');
        $position = $this->position($acme, 'Developer');
        $other = $this->owner('other@example.com');

        self::assertSame([], $this->queries->listForCompany($other->id, $acme->id())->items);
        self::assertNull($this->queries->findForOwner($other->id, $position->id()));
        self::assertNull($this->queries->findForOwner($this->me->id, PositionId::generate()));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $acme = $this->company($this->me, 'Acme');
        $position = $this->position($acme, 'Developer', '2020-01-15', '2023-06-30', 'Moved on', 'Backend work', '2024-01-02 03:04:05 UTC');

        $summary = $this->queries->findForOwner($this->me->id, $position->id());

        self::assertNotNull($summary);
        self::assertSame((string) $position->id(), $summary->id);
        self::assertSame((string) $acme->id(), $summary->companyId);
        self::assertSame('Developer', $summary->title);
        self::assertSame('Backend work', $summary->overview);
        self::assertSame('2020-01-15', $summary->startDate);
        self::assertSame('2023-06-30', $summary->endDate);
        self::assertFalse($summary->current);
        self::assertSame('Moved on', $summary->reasonLeft);
        self::assertSame('2024-01-02T03:04:05+00:00', $summary->createdAt->format(DATE_RFC3339));
        self::assertNull($summary->archivedAt);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testCurrentPositionsAcrossCompanies(): void
    {
        $acme = $this->company($this->me, 'Acme');
        $globex = $this->company($this->me, 'Globex');
        $gone = $this->company($this->me, 'Gone', archived: true);
        $this->position($acme, 'Consultant', '2023-01-01');
        $this->position($globex, 'Lead', '2024-01-01');
        $this->position($acme, 'Junior', '2020-01-01', '2022-12-31');
        $this->position($acme, 'Hidden', '2021-01-01', archived: true);
        $this->position($gone, 'At archived company', '2022-01-01');

        $current = $this->queries->listCurrentForOwner($this->me->id)->items;

        self::assertSame(['Lead', 'Consultant'], $this->titles($current));
        self::assertTrue($current[0]->current);
    }

    /**
     * @param list<PositionSummary> $summaries
     *
     * @return list<string>
     */
    private function titles(array $summaries): array
    {
        return array_map(static fn (PositionSummary $s) => $s->title, $summaries);
    }
}
