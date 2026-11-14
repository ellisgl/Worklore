<?php

declare(strict_types=1);

namespace App\Tests\Functional\Journal;

use App\Company\Company;
use App\Company\Product;
use App\Company\Project;
use App\Identity\User;
use App\Journal\JournalEntry;
use App\Journal\JournalEntryId;
use App\Journal\JournalEntryRepository;
use App\Journal\Query\JournalEntryQueries;
use App\Journal\Query\JournalEntrySummary;
use App\Journal\Query\JournalFilter;
use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\Query\TagRef;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\CreatesCompanyRecords;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function count;

use const DATE_RFC3339;

final class JournalEntryQueriesTest extends KernelTestCase
{
    use CreatesCompanyRecords;

    private EntityManagerInterface $em;
    private JournalEntryQueries $queries;
    private User $owner;
    private Company $acme;
    private Product $widget;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->queries = self::getContainer()->get(JournalEntryQueries::class);
        $this->owner = $this->owner();
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsNewestFirstAndScopedToTheProject(): void
    {
        $launch = $this->launch();
        $beta = $this->project($this->widget, 'Beta');
        $this->entry($launch, 'First', '2024-01-01 09:00');
        $this->entry($launch, 'Third', '2024-03-01 09:00');
        $this->entry($launch, 'Second', '2024-02-01 09:00');
        $this->entry($beta, 'Elsewhere', '2024-04-01 09:00');
        $this->em->flush();

        self::assertSame(['Third', 'Second', 'First'], $this->titles($this->queries->listForProject($this->owner->id, $launch->id())->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedEntryOrAncestorHidesEntries(): void
    {
        $project = $this->launch();
        $this->entry($project, 'Kickoff');
        $this->entry($project, 'Archived', archived: true);
        $this->em->flush();

        self::assertSame(['Kickoff'], $this->titles($this->queries->listForProject($this->owner->id, $project->id())->items));

        foreach (['project' => $project, 'product' => $this->widget, 'company' => $this->acme] as $level => $ancestor) {
            $ancestor->archive(new DateTimeImmutable());
            $this->saved($ancestor);
            self::assertSame([], $this->queries->listForProject($this->owner->id, $project->id())->items, "Archived $level");
            self::assertCount(2, $this->queries->listForProject($this->owner->id, $project->id(), includeArchived: true)->items, "Archived $level, includeArchived");
            $ancestor->unarchive();
            $this->saved($ancestor);
        }
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFieldsAndTags(): void
    {
        $project = $this->launch();
        $skill = new CategoryEntity($this->owner, 'Skill');
        $area = new CategoryEntity($this->owner, 'Area');
        $this->em->persist($skill);
        $this->em->persist($area);
        $php = $skill->addTag('php');
        $backend = $area->addTag('backend');
        $old = $area->addTag('api')->archive(new DateTimeImmutable());
        $entry = $this->entry($project, 'Kickoff', '2024-01-02 03:04:05', tags: [$php, $backend, $old]);
        $this->entry($project, 'Untagged');
        $this->em->flush();

        $summary = $this->queries->findForOwner($this->owner->id, $entry->id());

        self::assertNotNull($summary);
        self::assertSame((string) $entry->id(), $summary->id);
        self::assertSame((string) $project->id(), $summary->projectId);
        self::assertSame('Kickoff', $summary->title);
        self::assertSame('Launch', $summary->projectName);
        self::assertSame('Agreed on the scope.', $summary->entry);
        self::assertSame('2024-01-02T03:04:05+00:00', $summary->createdAt->format(DATE_RFC3339));
        self::assertNull($summary->archivedAt);
        self::assertSame(['Area/api', 'Area/backend', 'Skill/php'], $this->tagLabels($summary), 'By category, then name; archived tags too');
        self::assertSame((string) $php->id, $summary->tags[2]->id);
        self::assertSame((string) $skill->id, $summary->tags[2]->categoryId);

        $listed = $this->queries->listForProject($this->owner->id, $project->id())->items;
        self::assertSame([[], ['Area/api', 'Area/backend', 'Skill/php']], array_map($this->tagLabels(...), $listed), 'Each entry gets its own tags');
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    public function testListIsPaged(): void
    {
        $project = $this->launch();
        $this->entry($project, 'First', '2024-01-01');
        $this->entry($project, 'Second', '2024-02-01');
        $this->entry($project, 'Third', '2024-03-01');
        $this->em->flush();

        $page = $this->queries->listForProject($this->owner->id, $project->id(), page: new Page(2, 2));

        self::assertSame(['First'], $this->titles($page->items));
        self::assertSame(3, $page->total);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testOtherOwnersEntriesAreInvisible(): void
    {
        $other = $this->owner('other@example.com');
        $theirProject = $this->project($this->product($this->company($other, 'Theirs'), 'Theirs'), 'Theirs');
        $this->em->flush();
        $theirs = JournalEntry::write(JournalEntryId::generate(), $theirProject->id(), $other->id, 'Theirs', 'Body', [], new DateTimeImmutable());
        self::getContainer()->get(JournalEntryRepository::class)->save($theirs);

        self::assertSame([], $this->queries->listForProject($this->owner->id, $theirProject->id())->items);
        self::assertNull($this->queries->findForOwner($this->owner->id, $theirs->id()));
        self::assertNull($this->queries->findForOwner($this->owner->id, JournalEntryId::generate()));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testFeedCoversEveryProjectAndNamesIt(): void
    {
        $launch = $this->launch();
        $beta = $this->project($this->widget, 'Beta');
        $old = $this->project($this->widget, 'Old');
        $this->entry($launch, 'Kickoff', '2024-01-01');
        $this->entry($beta, 'Beta start', '2024-02-01');
        $this->entry($old, 'In archived project', '2024-03-01');
        $old->archive(new DateTimeImmutable());
        $this->saved($old);
        $this->em->flush();

        $feed = $this->queries->listForOwner($this->owner->id)->items;

        self::assertSame(['Beta start', 'Kickoff'], $this->titles($feed));
        self::assertSame(['Beta', 'Launch'], array_map(static fn (JournalEntrySummary $s) => $s->projectName, $feed));
        self::assertCount(3, $this->queries->listForOwner($this->owner->id, includeArchived: true)->items);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testFilterByTag(): void
    {
        $project = $this->launch();
        $area = new CategoryEntity($this->owner, 'Area');
        $this->em->persist($area);
        [$backend, $frontend] = [$area->addTag('backend'), $area->addTag('frontend')];
        $this->entry($project, 'Both', '2024-01-01', tags: [$backend, $frontend]);
        $this->entry($project, 'Backend', '2024-02-01', tags: [$backend]);
        $this->entry($project, 'Untagged', '2024-03-01');
        $this->em->flush();

        $byBackend = new JournalFilter(tagId: $backend->id);

        self::assertSame(['Backend', 'Both'], $this->titles($this->queries->listForProject($this->owner->id, $project->id(), filter: $byBackend)->items));
        self::assertSame(2, $this->queries->listForOwner($this->owner->id, filter: $byBackend)->total);
        $both = $this->queries->listForOwner($this->owner->id, filter: new JournalFilter(tagId: $frontend->id))->items;
        self::assertSame(['backend', 'frontend'], array_map(static fn (TagRef $t) => $t->name, $both[0]->tags), 'All its tags still listed');
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function searchProvider(): iterable
    {
        yield 'a word in the text' => ['database', ['Migration']];
        yield 'another form of the word' => ['deploying', ['Rollback', 'Release']];
        yield 'a word in the title' => ['kickoff', ['Kickoff']];
        yield 'any case' => ['DATABASE', ['Migration']];
        yield 'both words' => ['deploy staging', ['Release']];
        yield 'either word' => ['database or kickoff', ['Migration', 'Kickoff']];
        yield 'excluding a word' => ['deploy -staging', ['Rollback']];
        yield 'a phrase' => ['"to staging"', ['Release']];
        yield 'no match' => ['kubernetes', []];
        yield 'blank is no filter' => ['   ', ['Rollback', 'Release', 'Migration', 'Kickoff']];
    }

    /**
     * @param list<string> $titles newest first
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    #[DataProvider('searchProvider')]
    public function testSearch(string $search, array $titles): void
    {
        $project = $this->launch();
        $this->entry($project, 'Kickoff', '2024-01-01');
        $this->entry($project, 'Migration', '2024-02-01', 'Moved the database to the new cluster.');
        $this->entry($project, 'Release', '2024-03-01', 'Deployed version 2 to staging.');
        $this->entry($project, 'Rollback', '2024-04-01', 'Deploys failed in production; rolled back.');
        $this->em->flush();

        $found = $this->queries->listForProject($this->owner->id, $project->id(), filter: new JournalFilter(search: $search));

        self::assertSame($titles, $this->titles($found->items));
        self::assertSame(count($titles), $found->total);
    }

    /**
     * Acme's Widget's Launch project; Acme and Widget stay on the test.
     *
     * @throws DateMalformedStringException
     */
    private function launch(): Project
    {
        $this->acme = $this->company($this->owner, 'Acme');
        $this->widget = $this->product($this->acme, 'Widget');

        return $this->project($this->widget, 'Launch');
    }

    /**
     * @throws DateMalformedStringException
     */
    /**
     * Writes an entry through its aggregate, after saving the ORM-side
     * tags it refers to.
     *
     * @param list<TagEntity> $tags
     */
    private function entry(Project $project, string $title, string $at = 'now', string $text = 'Agreed on the scope.', array $tags = [], bool $archived = false): JournalEntry
    {
        $this->em->flush();
        $entry = JournalEntry::write(
            JournalEntryId::generate(),
            $project->id(),
            $this->owner->id,
            $title,
            $text,
            array_map(static fn (TagEntity $t) => $t->id, $tags),
            new DateTimeImmutable($at),
        );
        if ($archived) {
            $entry->archive(new DateTimeImmutable());
        }
        self::getContainer()->get(JournalEntryRepository::class)->save($entry);

        return $entry;
    }

    /**
     * @param list<JournalEntrySummary> $summaries
     *
     * @return list<string>
     */
    private function titles(array $summaries): array
    {
        return array_map(static fn (JournalEntrySummary $s) => $s->title, $summaries);
    }

    /**
     * @return list<string> "category/tag"
     */
    private function tagLabels(JournalEntrySummary $summary): array
    {
        return array_map(static fn (TagRef $t) => $t->categoryName.'/'.$t->name, $summary->tags);
    }
}
