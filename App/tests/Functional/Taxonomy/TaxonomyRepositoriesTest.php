<?php

declare(strict_types=1);

namespace App\Tests\Functional\Taxonomy;

use App\Identity\User;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Taxonomy\CategoryId;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\TagId;
use App\Taxonomy\TagRepository;
use App\Tests\Functional\CreatesRecords;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Categories and tags load from their events for their owner only, archived
 * or not; a stale copy can't be saved.
 */
final class TaxonomyRepositoriesTest extends KernelTestCase
{
    use CreatesRecords;

    private CategoryRepository $categories;
    private TagRepository $tags;
    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->categories = self::getContainer()->get(CategoryRepository::class);
        $this->tags = self::getContainer()->get(TagRepository::class);
        $this->me = $this->owner();
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testOwnerLoadsTheirRecordsArchivedOrNot(): void
    {
        $skill = $this->category($this->me, 'Skill', archived: true);
        $php = $this->tag($skill, 'php', archived: true);

        self::assertTrue($this->categories->findForOwner($skill->id(), $this->me->id)?->isArchived());
        self::assertNotNull($this->tags->findForOwner($php->id(), $this->me->id)?->archivedAt);
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testAnotherOwnerOrAnUnknownIdGetsNull(): void
    {
        $theirs = $this->category($this->owner('other@example.com'), 'Theirs');
        $theirTag = $this->tag($theirs);

        self::assertNull($this->categories->findForOwner($theirs->id(), $this->me->id));
        self::assertNull($this->tags->findForOwner($theirTag->id(), $this->me->id));
        self::assertNull($this->categories->findForOwner(CategoryId::generate(), $this->me->id));
        self::assertNull($this->tags->findForOwner(TagId::generate(), $this->me->id));
    }

    /**
     * @throws ConcurrentModification
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testAStaleCopyCannotBeSaved(): void
    {
        $id = $this->tag($this->category($this->me))->id();
        $first = $this->tags->findForOwner($id, $this->me->id);
        $second = $this->tags->findForOwner($id, $this->me->id);
        self::assertNotNull($first);
        self::assertNotNull($second);

        $first->rename('first');
        $this->tags->save($first);
        $second->archive(new DateTimeImmutable());

        $this->expectException(ConcurrentModification::class);
        $this->tags->save($second);
    }
}
