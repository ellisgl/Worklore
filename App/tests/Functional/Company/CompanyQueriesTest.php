<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\CompanyEntity;
use App\Company\CompanyId;
use App\Company\Query\CompanyQueries;
use App\Company\Query\CompanySummary;
use App\Identity\User;
use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use const DATE_RFC3339;

final class CompanyQueriesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CompanyQueries $queries;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->queries = self::getContainer()->get(CompanyQueries::class);
        $this->owner = new User('me@example.com');
        $this->em->persist($this->owner);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsSortedByNameAndScopedToTheOwner(): void
    {
        $this->company('Globex');
        $this->company('Acme');
        $this->company('Initech')->archive(new DateTimeImmutable('2024-05-06 07:08:09'));
        $other = new User('other@example.com');
        $this->em->persist($other);
        $this->em->persist(new CompanyEntity($other, 'Theirs'));
        $this->em->flush();

        self::assertSame(['Acme', 'Globex'], $this->names($this->queries->listForOwner($this->owner->id)->items));
        self::assertSame(['Acme', 'Globex', 'Initech'], $this->names($this->queries->listForOwner($this->owner->id, includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $acme = $this->company('Acme', 'Widgets', new DateTimeImmutable('2024-01-02 03:04:05', new DateTimeZone('UTC')));
        $acme->addProduct('Widget');
        $acme->addPosition('Old', new DateTimeImmutable('2020-01-01'))->end(new DateTimeImmutable('2021-01-01'));
        $acme->addPosition('Lead', new DateTimeImmutable('2022-01-01'));
        $acme->addPosition('Hidden', new DateTimeImmutable('2023-01-01'))->archive(new DateTimeImmutable());
        $this->em->flush();

        [$summary] = $this->queries->listForOwner($this->owner->id)->items;

        self::assertSame((string) $acme->id, $summary->id);
        self::assertSame('Widgets', $summary->overview);
        self::assertSame('2024-01-02T03:04:05+00:00', $summary->createdAt->format(DATE_RFC3339));
        self::assertNull($summary->archivedAt);
        self::assertSame(1, $summary->productCount);
        self::assertSame('Lead', $summary->currentPosition, 'Newest current, non-archived position');
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testOwnerWithoutCompaniesGetsEmptyList(): void
    {
        $this->em->flush();

        self::assertSame([], $this->queries->listForOwner($this->owner->id)->items);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testFindReturnsOwnCompanyIncludingArchived(): void
    {
        $acme = $this->company('Acme');
        $gone = $this->company('Gone')->archive(new DateTimeImmutable('2024-01-01'));
        $this->em->flush();

        self::assertSame('Acme', $this->queries->findForOwner($this->owner->id, $acme->id)?->name);
        self::assertNotNull($this->queries->findForOwner($this->owner->id, $gone->id)?->archivedAt);
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
        $this->em->flush();

        self::assertNull($this->queries->findForOwner($this->owner->id, $theirs->id));
        self::assertNull($this->queries->findForOwner($this->owner->id, CompanyId::generate()));
    }

    private function company(string $name, ?string $overview = null, ?DateTimeImmutable $createdAt = null): CompanyEntity
    {
        $company = new CompanyEntity($this->owner, $name, $overview, $createdAt);
        $this->em->persist($company);

        return $company;
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    public function testListIsPaged(): void
    {
        foreach (['E', 'C', 'A', 'D', 'B'] as $name) {
            $this->em->persist(new CompanyEntity($this->owner, $name));
        }
        $this->em->persist(new CompanyEntity($this->owner, 'Archived')->archive(new DateTimeImmutable()));
        $this->em->flush();

        $second = $this->queries->listForOwner($this->owner->id, page: new Page(2, 2));
        $last = $this->queries->listForOwner($this->owner->id, page: new Page(3, 2));
        $beyond = $this->queries->listForOwner($this->owner->id, page: new Page(4, 2));

        self::assertSame(['C', 'D'], $this->names($second->items));
        self::assertSame(2, $second->page);
        self::assertSame(2, $second->perPage);
        self::assertSame(5, $second->total, 'Archived left out of the total too');
        self::assertSame(['E'], $this->names($last->items));
        self::assertSame([], $beyond->items);
        self::assertSame(5, $beyond->total);
        self::assertSame(6, $this->queries->listForOwner($this->owner->id, includeArchived: true)->total);
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
