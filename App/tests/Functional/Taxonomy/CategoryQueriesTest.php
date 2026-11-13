<?php

declare(strict_types=1);

namespace App\Tests\Functional\Taxonomy;

use App\Identity\User;
use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\CategoryId;
use App\Taxonomy\Query\CategoryQueries;
use App\Taxonomy\Query\CategorySummary;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

use const DATE_RFC3339;

final class CategoryQueriesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CategoryQueries $queries;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->queries = self::getContainer()->get(CategoryQueries::class);
        $this->owner = new User('me@example.com');
        $this->em->persist($this->owner);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsSortedByNameAndScopedToTheOwner(): void
    {
        // Added out of order on purpose
        $this->category('skill');
        $this->category('area');
        $this->category('location');
        $other = new User('other@example.com');
        $this->em->persist($other);
        $this->em->persist(new CategoryEntity($other, 'theirs'));
        $this->em->flush();

        self::assertSame(['area', 'location', 'skill'], $this->names($this->queries->listForOwner($this->owner->id)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedCategoriesAreLeftOutUnlessAsked(): void
    {
        $this->category('active');
        $this->category('archived')->archive(new DateTimeImmutable());
        $this->em->flush();

        self::assertSame(['active'], $this->names($this->queries->listForOwner($this->owner->id)->items));
        self::assertSame(['active', 'archived'], $this->names($this->queries->listForOwner($this->owner->id, includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testSameNamesSortInCreationOrder(): void
    {
        $first = $this->category('Area', 'first');
        $second = $this->category('Area', 'second');
        $this->em->flush();

        $listed = $this->queries->listForOwner($this->owner->id)->items;

        self::assertSame([(string) $first->id, (string) $second->id], array_map(static fn (CategorySummary $c) => $c->id, $listed));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $area = new CategoryEntity($this->owner, 'Area', 'Where I work', new DateTimeImmutable('2024-01-02 03:04:05'));
        $this->em->persist($area);
        $area->addTag('backend');
        $area->addTag('frontend');
        $area->addTag('old')->archive(new DateTimeImmutable());
        $this->em->flush();

        $summary = $this->queries->findForOwner($this->owner->id, $area->id);

        self::assertNotNull($summary);
        self::assertSame((string) $area->id, $summary->id);
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
            $this->category($name);
        }
        $this->em->flush();

        $page = $this->queries->listForOwner($this->owner->id, page: new Page(2, 2));

        self::assertSame(['c'], $this->names($page->items));
        self::assertSame(3, $page->total);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testFindAndExistsIgnoreOtherOwnersAndUnknownIds(): void
    {
        $other = new User('other@example.com');
        $this->em->persist($other);
        $theirs = new CategoryEntity($other, 'theirs');
        $this->em->persist($theirs);
        $mine = $this->category('mine')->archive(new DateTimeImmutable());
        $this->em->flush();

        self::assertNull($this->queries->findForOwner($this->owner->id, $theirs->id));
        self::assertNull($this->queries->findForOwner($this->owner->id, CategoryId::generate()));
        self::assertNotNull($this->queries->findForOwner($this->owner->id, $mine->id), 'Archived ones too');
        self::assertFalse($this->queries->existsForOwner($this->owner->id, $theirs->id));
        self::assertTrue($this->queries->existsForOwner($this->owner->id, $mine->id));
    }

    private function category(string $name, ?string $description = null): CategoryEntity
    {
        $category = new CategoryEntity($this->owner, $name, $description);
        $this->em->persist($category);

        return $category;
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
