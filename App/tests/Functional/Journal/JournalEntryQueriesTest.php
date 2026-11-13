<?php

declare(strict_types=1);

namespace App\Tests\Functional\Journal;

use App\Company\CompanyEntity;
use App\Company\ProjectEntity;
use App\Identity\User;
use App\Journal\JournalEntryEntity;
use App\Journal\JournalEntryId;
use App\Journal\Query\JournalEntryQueries;
use App\Journal\Query\JournalEntrySummary;
use App\Journal\Query\JournalTag;
use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use App\Taxonomy\CategoryEntity;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

use const DATE_RFC3339;

final class JournalEntryQueriesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private JournalEntryQueries $queries;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->queries = self::getContainer()->get(JournalEntryQueries::class);
        $this->owner = new User('me@example.com');
        $this->em->persist($this->owner);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testListIsNewestFirstAndScopedToTheProject(): void
    {
        $launch = $this->project();
        $beta = $launch->product->addProject('Beta');
        $this->entry($launch, 'First', '2024-01-01 09:00');
        $this->entry($launch, 'Third', '2024-03-01 09:00');
        $this->entry($launch, 'Second', '2024-02-01 09:00');
        $this->entry($beta, 'Elsewhere', '2024-04-01 09:00');
        $this->em->flush();

        self::assertSame(['Third', 'Second', 'First'], $this->titles($this->queries->listForProject($this->owner->id, $launch->id)->items));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testArchivedEntryOrAncestorHidesEntries(): void
    {
        $project = $this->project();
        $this->entry($project, 'Kickoff');
        $this->entry($project, 'Archived')->archive(new DateTimeImmutable());
        $this->em->flush();

        self::assertSame(['Kickoff'], $this->titles($this->queries->listForProject($this->owner->id, $project->id)->items));

        foreach (['project' => $project, 'product' => $project->product, 'company' => $project->product->company] as $level => $ancestor) {
            $ancestor->archive(new DateTimeImmutable());
            $this->em->flush();
            self::assertSame([], $this->queries->listForProject($this->owner->id, $project->id)->items, "Archived $level");
            self::assertCount(2, $this->queries->listForProject($this->owner->id, $project->id, includeArchived: true)->items, "Archived $level, includeArchived");
            $ancestor->unarchive();
            $this->em->flush();
        }
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testReadModelFieldsAndTags(): void
    {
        $project = $this->project();
        $skill = new CategoryEntity($this->owner, 'Skill');
        $area = new CategoryEntity($this->owner, 'Area');
        $this->em->persist($skill);
        $this->em->persist($area);
        $php = $skill->addTag('php');
        $backend = $area->addTag('backend');
        $old = $area->addTag('api')->archive(new DateTimeImmutable());
        $entry = $this->entry($project, 'Kickoff', '2024-01-02 03:04:05')->setTags($php, $backend, $old);
        $this->entry($project, 'Untagged');
        $this->em->flush();

        $summary = $this->queries->findForOwner($this->owner->id, $entry->id);

        self::assertNotNull($summary);
        self::assertSame((string) $entry->id, $summary->id);
        self::assertSame((string) $project->id, $summary->projectId);
        self::assertSame('Kickoff', $summary->title);
        self::assertSame('Agreed on the scope.', $summary->entry);
        self::assertSame('2024-01-02T03:04:05+00:00', $summary->createdAt->format(DATE_RFC3339));
        self::assertNull($summary->archivedAt);
        self::assertSame(['Area/api', 'Area/backend', 'Skill/php'], $this->tagLabels($summary), 'By category, then name; archived tags too');
        self::assertSame((string) $php->id, $summary->tags[2]->id);
        self::assertSame((string) $skill->id, $summary->tags[2]->categoryId);

        $listed = $this->queries->listForProject($this->owner->id, $project->id)->items;
        self::assertSame([[], ['Area/api', 'Area/backend', 'Skill/php']], array_map($this->tagLabels(...), $listed), 'Each entry gets its own tags');
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    public function testListIsPaged(): void
    {
        $project = $this->project();
        $this->entry($project, 'First', '2024-01-01');
        $this->entry($project, 'Second', '2024-02-01');
        $this->entry($project, 'Third', '2024-03-01');
        $this->em->flush();

        $page = $this->queries->listForProject($this->owner->id, $project->id, page: new Page(2, 2));

        self::assertSame(['First'], $this->titles($page->items));
        self::assertSame(3, $page->total);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testOtherOwnersEntriesAreInvisible(): void
    {
        $other = new User('other@example.com');
        $this->em->persist($other);
        $theirProject = new CompanyEntity($other, 'Theirs')->addProduct('Theirs')->addProject('Theirs');
        $this->em->persist($theirProject->product->company);
        $theirs = new JournalEntryEntity($theirProject, $other, 'Theirs', 'Body');
        $this->em->persist($theirs);
        $this->em->flush();

        self::assertSame([], $this->queries->listForProject($this->owner->id, $theirProject->id)->items);
        self::assertNull($this->queries->findForOwner($this->owner->id, $theirs->id));
        self::assertNull($this->queries->findForOwner($this->owner->id, JournalEntryId::generate()));
    }

    private function project(): ProjectEntity
    {
        $company = new CompanyEntity($this->owner, 'Acme');
        $this->em->persist($company);

        return $company->addProduct('Widget')->addProject('Launch');
    }

    /**
     * @throws DateMalformedStringException
     */
    private function entry(ProjectEntity $project, string $title, string $at = 'now'): JournalEntryEntity
    {
        $entry = new JournalEntryEntity($project, $this->owner, $title, 'Agreed on the scope.', new DateTimeImmutable($at));
        $this->em->persist($entry);

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
        return array_map(static fn (JournalTag $t) => $t->categoryName.'/'.$t->name, $summary->tags);
    }
}
