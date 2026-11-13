<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\CompanyEntity;
use App\Company\PositionEntity;
use App\Company\PositionId;
use App\Company\Query\PositionQueries;
use App\Company\Query\PositionSummary;
use App\Identity\User;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

final class PositionQueriesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PositionQueries $queries;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->queries = self::getContainer()->get(PositionQueries::class);
        $this->owner = new User('me@example.com');
        $this->em->persist($this->owner);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsNewestFirstAndScopedToTheCompany(): void
    {
        $acme = $this->company('Acme');
        $globex = $this->company('Globex');
        // Added out of order on purpose
        $this->position($acme, 'Senior Developer', '2022-06-01');
        $this->position($acme, 'Intern', '2018-06-01');
        $this->position($acme, 'Developer', '2019-03-01');
        $this->position($globex, 'Manager');
        $this->em->flush();

        self::assertSame(['Senior Developer', 'Developer', 'Intern'], $this->titles($this->queries->listForCompany($this->owner->id, $acme->id)));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedPositionsAreLeftOutUnlessAsked(): void
    {
        $acme = $this->company('Acme');
        $this->position($acme, 'Developer', '2019-03-01');
        $this->position($acme, 'Archived', '2020-01-01')->archive(new DateTimeImmutable());
        $this->em->flush();

        self::assertSame(['Developer'], $this->titles($this->queries->listForCompany($this->owner->id, $acme->id)));
        self::assertSame(['Archived', 'Developer'], $this->titles($this->queries->listForCompany($this->owner->id, $acme->id, includeArchived: true)));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedCompanyHidesItsPositions(): void
    {
        $acme = $this->company('Acme')->archive(new DateTimeImmutable());
        $this->position($acme, 'Developer');
        $this->em->flush();

        self::assertSame([], $this->queries->listForCompany($this->owner->id, $acme->id));
        self::assertSame(['Developer'], $this->titles($this->queries->listForCompany($this->owner->id, $acme->id, includeArchived: true)));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testAnotherOwnerGetsNothing(): void
    {
        $acme = $this->company('Acme');
        $position = $this->position($acme, 'Developer');
        $other = new User('other@example.com');
        $this->em->persist($other);
        $this->em->flush();

        self::assertSame([], $this->queries->listForCompany($other->id, $acme->id));
        self::assertNull($this->queries->findForOwner($other->id, $position->id));
        self::assertNull($this->queries->findForOwner($this->owner->id, PositionId::generate()));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $acme = $this->company('Acme');
        $position = $acme->addPosition('Developer', new DateTimeImmutable('2020-01-15'), 'Backend work', new DateTimeImmutable('2024-01-02 03:04:05'))
            ->end(new DateTimeImmutable('2023-06-30'), 'Moved on');
        $this->em->flush();

        $summary = $this->queries->findForOwner($this->owner->id, $position->id);

        self::assertNotNull($summary);
        self::assertSame((string) $position->id, $summary->id);
        self::assertSame((string) $acme->id, $summary->companyId);
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
        $acme = $this->company('Acme');
        $globex = $this->company('Globex');
        $gone = $this->company('Gone')->archive(new DateTimeImmutable());
        $this->position($acme, 'Consultant', '2023-01-01');
        $this->position($globex, 'Lead', '2024-01-01');
        $this->position($acme, 'Junior', '2020-01-01')->end(new DateTimeImmutable('2022-12-31'));
        $this->position($acme, 'Hidden', '2021-01-01')->archive(new DateTimeImmutable());
        $this->position($gone, 'At archived company', '2022-01-01');
        $this->em->flush();

        $current = $this->queries->listCurrentForOwner($this->owner->id);

        self::assertSame(['Lead', 'Consultant'], $this->titles($current));
        self::assertTrue($current[0]->current);
    }

    private function company(string $name): CompanyEntity
    {
        $company = new CompanyEntity($this->owner, $name);
        $this->em->persist($company);

        return $company;
    }

    /**
     * @throws DateMalformedStringException
     */
    private function position(CompanyEntity $company, string $title, string $start = '2024-01-15'): PositionEntity
    {
        return $company->addPosition($title, new DateTimeImmutable($start));
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
