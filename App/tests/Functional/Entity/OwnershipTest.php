<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Identity\User;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\FindsEntities;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
    use FindsEntities;

    private User $me;
    private User $other;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->me = new User('me@example.com');
        $this->other = new User('other@example.com');
    }

    /**
     * @return iterable<string, array{Closure(User): (TagEntity)}>
     */
    public static function childProvider(): iterable
    {
        yield 'tag' => [static fn (User $owner) => new CategoryEntity($owner, 'Area')->addTag('php')];
    }

    /**
     * @param Closure(User): (TagEntity) $create
     */
    #[DataProvider('childProvider')]
    public function testChildrenTakeTheirRootsOwner(Closure $create): void
    {
        self::assertSame($this->me, $create($this->me)->owner);
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
}
