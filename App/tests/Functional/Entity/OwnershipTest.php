<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Company\ProductEntity;
use App\Identity\User;
use App\Journal\JournalEntryEntity;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\CategoryEntity;
use Closure;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function assert;

/**
 * Records owned by different users can't be linked.
 *
 * Inside an aggregate this holds by construction: children are created by
 * their root and take its owner. Across aggregates (a journal entry's
 * project, tags on products, projects and entries, a tag moving category)
 * it's checked.
 */
final class OwnershipTest extends KernelTestCase
{
    private User $me;
    private User $other;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->me = new User('me@example.com');
        $this->other = new User('other@example.com');
    }

    /**
     * @return iterable<string, array{Closure(User $owner): object}>
     */
    public static function childProvider(): iterable
    {
        yield 'product' => [static fn (User $owner) => self::product($owner)];
        yield 'project' => [static fn (User $owner) => self::product($owner)->addProject('Launch')];
        yield 'position' => [static fn (User $owner) => new CompanyEntity($owner, 'Acme')->addPosition('Dev', new DateTimeImmutable('2024-01-01'))];
        yield 'tag' => [static fn (User $owner) => new CategoryEntity($owner, 'Area')->addTag('php')];
    }

    #[DataProvider('childProvider')]
    public function testChildrenTakeTheirRootsOwner(Closure $create): void
    {
        self::assertSame($this->me, $create($this->me)->owner);
    }

    public function testCannotWriteAJournalEntryInAnotherUsersProject(): void
    {
        $theirProject = self::product($this->other)->addProject('Launch');

        $this->expectException(OwnershipMismatchException::class);
        new JournalEntryEntity($theirProject, $this->me, 'Title', 'Body');
    }

    /**
     * @return iterable<string, array{Closure(User $me): object}>
     */
    public static function taggableProvider(): iterable
    {
        yield 'product' => [static fn (User $me) => self::product($me)];
        yield 'project' => [static fn (User $me) => self::product($me)->addProject('Launch')];
        yield 'journal entry' => [static fn (User $me) => new JournalEntryEntity(self::product($me)->addProject('Launch'), $me, 'Title', 'Body')];
    }

    #[DataProvider('taggableProvider')]
    public function testCannotAddAnotherUsersTag(Closure $create): void
    {
        $theirTag = new CategoryEntity($this->other, 'Area')->addTag('php');

        $this->expectException(OwnershipMismatchException::class);
        $create($this->me)->addTag($theirTag);
    }

    #[DataProvider('taggableProvider')]
    public function testCanAddOwnTag(Closure $create): void
    {
        $myTag = new CategoryEntity($this->me, 'Area')->addTag('php');

        $record = $create($this->me)->addTag($myTag);

        self::assertTrue($record->hasTag($myTag));
    }

    public function testCannotMoveTagIntoAnotherUsersCategory(): void
    {
        $tag = new CategoryEntity($this->me, 'Area')->addTag('php');

        $this->expectException(OwnershipMismatchException::class);
        $tag->setCategory(new CategoryEntity($this->other, 'Theirs'));
    }

    public function testFailedTagMoveLeavesTagWhereItWas(): void
    {
        $area = new CategoryEntity($this->me, 'Area');
        $tag = $area->addTag('php');

        try {
            $tag->setCategory(new CategoryEntity($this->other, 'Theirs'));
        } catch (OwnershipMismatchException) {
        }

        self::assertSame($area, $tag->category);
        self::assertTrue($area->getTags()->contains($tag));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testSameUserLoadedSeparatelyStillMatches(): void
    {
        // The check compares ids, so a lazy reference to the same user passes
        // even though it isn't the same object as the loaded project's user.
        $em = self::getContainer()->get('doctrine')->getManager();
        assert($em instanceof EntityManagerInterface);
        $project = self::product($this->me)->addProject('Launch');
        $em->persist($this->me);
        $em->persist($project->product->company);
        $em->flush();
        $em->clear();

        $project = $em->find($project::class, $project->id);
        $sameUser = $em->getReference(User::class, $this->me->id);

        $entry = new JournalEntryEntity($project, $sameUser, 'Kickoff', 'Body');

        self::assertSame($project, $entry->project);
    }

    private static function product(User $owner): ProductEntity
    {
        return new CompanyEntity($owner, 'Acme')->addProduct('Widget');
    }
}
