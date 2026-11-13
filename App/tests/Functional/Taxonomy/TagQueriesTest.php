<?php

declare(strict_types=1);

namespace App\Tests\Functional\Taxonomy;

use App\Identity\User;
use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\Query\TagQueries;
use App\Taxonomy\Query\TagSummary;
use App\Taxonomy\TagId;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

use const DATE_RFC3339;

final class TagQueriesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TagQueries $queries;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->queries = self::getContainer()->get(TagQueries::class);
        $this->owner = new User('me@example.com');
        $this->em->persist($this->owner);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testAllTagsAreSortedByCategoryThenName(): void
    {
        // Added out of order on purpose
        $skill = $this->category('skill');
        $area = $this->category('area');
        $skill->addTag('sql');
        $area->addTag('frontend');
        $skill->addTag('php');
        $area->addTag('backend');
        $this->em->flush();

        self::assertSame(
            ['area/backend', 'area/frontend', 'skill/php', 'skill/sql'],
            $this->labels($this->queries->listForOwner($this->owner->id)->items),
        );
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedTagsAndTagsInArchivedCategoriesAreLeftOutUnlessAsked(): void
    {
        $area = $this->category('area');
        $old = $this->category('old')->archive(new DateTimeImmutable());
        $area->addTag('active');
        $area->addTag('archived')->archive(new DateTimeImmutable());
        $old->addTag('in-archived');
        $this->em->flush();

        self::assertSame(['area/active'], $this->labels($this->queries->listForOwner($this->owner->id)->items));
        self::assertSame(
            ['area/active', 'area/archived', 'old/in-archived'],
            $this->labels($this->queries->listForOwner($this->owner->id, includeArchived: true)->items),
        );
        self::assertSame([], $this->queries->listForCategory($this->owner->id, $old->id)->items);
        self::assertSame(['in-archived'], $this->names($this->queries->listForCategory($this->owner->id, $old->id, includeArchived: true)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testCategoryListIsSortedByNameAndScoped(): void
    {
        $area = $this->category('area');
        $skill = $this->category('skill');
        $area->addTag('frontend');
        $area->addTag('backend');
        $skill->addTag('php');
        $this->em->flush();

        self::assertSame(['backend', 'frontend'], $this->names($this->queries->listForCategory($this->owner->id, $area->id)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    public function testListsArePaged(): void
    {
        $area = $this->category('area');
        foreach (['c', 'a', 'b'] as $name) {
            $area->addTag($name);
        }
        $this->em->flush();

        $page = $this->queries->listForCategory($this->owner->id, $area->id, page: new Page(2, 2));

        self::assertSame(['c'], $this->names($page->items));
        self::assertSame(3, $page->total);
        self::assertSame(3, $this->queries->listForOwner($this->owner->id, page: new Page(1, 1))->total);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFields(): void
    {
        $area = $this->category('Area');
        $tag = $area->addTag('backend', new DateTimeImmutable('2024-01-02 03:04:05'));
        $this->em->flush();

        $summary = $this->queries->findForOwner($this->owner->id, $tag->id);

        self::assertNotNull($summary);
        self::assertSame((string) $tag->id, $summary->id);
        self::assertSame((string) $area->id, $summary->categoryId);
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
        $other = new User('other@example.com');
        $this->em->persist($other);
        $theirs = new CategoryEntity($other, 'theirs');
        $this->em->persist($theirs);
        $tag = $theirs->addTag('theirs');
        $this->category('mine')->addTag('mine');
        $this->em->flush();

        self::assertSame(['mine'], $this->names($this->queries->listForOwner($this->owner->id)->items));
        self::assertSame([], $this->queries->listForCategory($this->owner->id, $theirs->id)->items);
        self::assertNull($this->queries->findForOwner($this->owner->id, $tag->id));
        self::assertNull($this->queries->findForOwner($this->owner->id, TagId::generate()));
    }

    private function category(string $name): CategoryEntity
    {
        $category = new CategoryEntity($this->owner, $name);
        $this->em->persist($category);

        return $category;
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
