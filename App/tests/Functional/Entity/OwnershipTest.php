<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Company\PositionEntity;
use App\Company\ProductEntity;
use App\Company\ProjectEntity;
use App\Entity\OwnershipMismatchException;
use App\Entity\User;
use App\Journal\JournalEntryEntity;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Records owned by different users can't be linked: not as parent/child, and
 * not through tags.
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
     * @return iterable<string, array{\Closure(User $me, User $other): mixed}>
     */
    public static function crossUserParentProvider(): iterable
    {
        yield 'product under their company' => [static fn (User $me, User $other) => new ProductEntity(new CompanyEntity($other, 'Theirs'), $me, 'Widget')];
        yield 'position at their company' => [static fn (User $me, User $other) => new PositionEntity(new CompanyEntity($other, 'Theirs'), $me, 'Dev', new \DateTimeImmutable('2024-01-01'))];
        yield 'project under their product' => [static fn (User $me, User $other) => new ProjectEntity(self::product($other), $me, 'Launch')];
        yield 'journal entry in their project' => [static fn (User $me, User $other) => new JournalEntryEntity(new ProjectEntity(self::product($other), $other, 'Launch'), $me, 'Title', 'Body')];
        yield 'tag in their category' => [static fn (User $me, User $other) => new TagEntity(new CategoryEntity($other, 'Area'), $me, 'php')];
    }

    #[DataProvider('crossUserParentProvider')]
    public function testCannotCreateUnderAnotherUsersParent(\Closure $create): void
    {
        $this->expectException(OwnershipMismatchException::class);
        $create($this->me, $this->other);
    }

    /**
     * @return iterable<string, array{\Closure(User $me): object}>
     */
    public static function taggableProvider(): iterable
    {
        yield 'product' => [static fn (User $me) => self::product($me)];
        yield 'project' => [static fn (User $me) => new ProjectEntity(self::product($me), $me, 'Launch')];
        yield 'journal entry' => [static fn (User $me) => new JournalEntryEntity(new ProjectEntity(self::product($me), $me, 'Launch'), $me, 'Title', 'Body')];
    }

    #[DataProvider('taggableProvider')]
    public function testCannotAddAnotherUsersTag(\Closure $create): void
    {
        $theirTag = new TagEntity(new CategoryEntity($this->other, 'Area'), $this->other, 'php');

        $this->expectException(OwnershipMismatchException::class);
        $create($this->me)->addTag($theirTag);
    }

    #[DataProvider('taggableProvider')]
    public function testCanAddOwnTag(\Closure $create): void
    {
        $myTag = new TagEntity(new CategoryEntity($this->me, 'Area'), $this->me, 'php');

        $record = $create($this->me)->addTag($myTag);

        self::assertTrue($record->hasTag($myTag));
    }

    public function testCannotMoveTagIntoAnotherUsersCategory(): void
    {
        $tag = new TagEntity(new CategoryEntity($this->me, 'Area'), $this->me, 'php');

        $this->expectException(OwnershipMismatchException::class);
        $tag->setCategory(new CategoryEntity($this->other, 'Theirs'));
    }

    public function testFailedTagMoveLeavesTagWhereItWas(): void
    {
        $area = new CategoryEntity($this->me, 'Area');
        $tag = new TagEntity($area, $this->me, 'php');

        try {
            $tag->setCategory(new CategoryEntity($this->other, 'Theirs'));
        } catch (OwnershipMismatchException) {
        }

        self::assertSame($area, $tag->category);
        self::assertTrue($area->getTags()->contains($tag));
    }

    public function testSameUserLoadedSeparatelyStillMatches(): void
    {
        // The check compares ids, so a lazy reference to the same user passes
        // even though it isn't the same object as the loaded company's user.
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);
        $company = new CompanyEntity($this->me, 'Acme');
        $em->persist($this->me);
        $em->persist($company);
        $em->flush();
        $em->clear();

        $company = $em->find(CompanyEntity::class, $company->getId());
        $sameUser = $em->getReference(User::class, $this->me->getId());

        $product = new ProductEntity($company, $sameUser, 'Widget');

        self::assertSame($company, $product->company);
    }

    private static function product(User $owner): ProductEntity
    {
        return new ProductEntity(new CompanyEntity($owner, 'Acme'), $owner, 'Widget');
    }
}
