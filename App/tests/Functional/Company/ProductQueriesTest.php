<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\CompanyEntity;
use App\Company\ProductId;
use App\Company\Query\ProductQueries;
use App\Company\Query\ProductSummary;
use App\Identity\User;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductQueriesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ProductQueries $queries;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->queries = self::getContainer()->get(ProductQueries::class);
        $this->owner = new User('me@example.com');
        $this->em->persist($this->owner);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsSortedByNameAndScopedToTheCompany(): void
    {
        $acme = $this->company('Acme');
        $globex = $this->company('Globex');
        $acme->addProduct('Widget');
        $acme->addProduct('Gadget');
        $acme->addProduct('Retired')->archive(new DateTimeImmutable());
        $globex->addProduct('Other');
        $this->em->flush();

        self::assertSame(['Gadget', 'Widget'], $this->names($this->queries->listForCompany($this->owner->id, $acme->id)->items));
        self::assertSame(['Gadget', 'Retired', 'Widget'], $this->names($this->queries->listForCompany($this->owner->id, $acme->id, includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedCompanyHidesItsProducts(): void
    {
        $acme = $this->company('Acme')->archive(new DateTimeImmutable());
        $acme->addProduct('Widget');
        $this->em->flush();

        self::assertSame([], $this->queries->listForCompany($this->owner->id, $acme->id)->items);
        self::assertSame(['Widget'], $this->names($this->queries->listForCompany($this->owner->id, $acme->id, includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $acme = $this->company('Acme');
        $widget = $acme->addProduct('Widget', 'Our widget', new DateTimeImmutable('2024-01-02 03:04:05'));
        $widget->addProject('Launch');
        $widget->addProject('Old')->archive(new DateTimeImmutable());
        $this->em->flush();

        [$summary] = $this->queries->listForCompany($this->owner->id, $acme->id)->items;

        self::assertSame((string) $widget->id, $summary->id);
        self::assertSame((string) $acme->id, $summary->companyId);
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
        $widget = $this->company('Acme')->addProduct('Widget')->archive(new DateTimeImmutable('2024-01-01'));
        $this->em->flush();

        self::assertNotNull($this->queries->findForOwner($this->owner->id, $widget->id)?->archivedAt);
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
        $product = $theirs->addProduct('Theirs');
        $this->em->flush();

        self::assertNull($this->queries->findForOwner($this->owner->id, $product->id));
        self::assertNull($this->queries->findForOwner($this->owner->id, ProductId::generate()));
    }

    private function company(string $name): CompanyEntity
    {
        $company = new CompanyEntity($this->owner, $name);
        $this->em->persist($company);

        return $company;
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
