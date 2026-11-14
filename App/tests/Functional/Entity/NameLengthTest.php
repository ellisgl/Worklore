<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\FindsEntities;
use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Names and titles are VARCHAR(255). The entities reject longer text with a
 * clear message instead of letting PostgreSQL fail the insert.
 */
final class NameLengthTest extends KernelTestCase
{
    use FindsEntities;

    protected function setUp(): void
    {
        self::bootKernel();
    }

    /**
     * Each closure builds the record with the given name/title and returns
     * every entity that needs persisting, the named record last.
     *
     * @return iterable<string, array{Closure(User, string): non-empty-list<CategoryEntity|TagEntity>}>
     */
    public static function namedProvider(): iterable
    {
        yield 'category' => [static fn (User $u, string $n) => [new CategoryEntity($u, $n)]];
        yield 'tag' => [static function (User $u, string $n) {
            $c = new CategoryEntity($u, 'Area');

            return [$c, $c->addTag($n)];
        }];
    }

    /**
     * @param Closure(User, string): non-empty-list<CategoryEntity|TagEntity> $build
     */
    #[DataProvider('namedProvider')]
    public function testOneOverMaxLengthIsRejected(Closure $build): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be longer than 255 characters/');
        $build(new User('a@example.com'), str_repeat('x', 256));
    }
}
