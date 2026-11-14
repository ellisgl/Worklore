<?php

declare(strict_types=1);

namespace App\Tests\Unit\Taxonomy;

use App\Shared\InvalidValueException;
use App\Shared\UserId;
use App\Taxonomy\Category;
use App\Taxonomy\CategoryId;
use App\Taxonomy\Event\CategoryArchived;
use App\Taxonomy\Event\CategoryDescriptionChanged;
use App\Taxonomy\Event\CategoryRenamed;
use App\Taxonomy\Event\TagMoved;
use App\Taxonomy\Event\TagRenamed;
use App\Taxonomy\Tag;
use App\Taxonomy\TagId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Given their history, when categories and tags are told to change, then
 * they record these events (or refuse).
 */
final class TaxonomyAggregatesTest extends TestCase
{
    public function testACategoryRecordsOnlyRealChanges(): void
    {
        $category = Category::fromHistory(Category::create(CategoryId::generate(), UserId::generate(), ' Skill ', ' Things I can do ', new DateTimeImmutable())->releaseEvents());

        $category->rename('Skill');
        $category->describe('Things I can do');
        $category->unarchive();
        self::assertSame([], $category->releaseEvents());

        $category->rename('Skills');
        $category->describe('  ');
        $category->archive(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));
        $category->archive(new DateTimeImmutable('2026-02-01 00:00:00 UTC'));

        self::assertSame([CategoryRenamed::class, CategoryDescriptionChanged::class, CategoryArchived::class], self::classes($category->releaseEvents()));
        self::assertSame('Skills', $category->name->value);
        self::assertNull($category->description, 'Blank clears it');
        self::assertSame('2026-01-01', $category->archivedAt?->format('Y-m-d'), 'Archiving twice keeps the first time');
    }

    public function testBlankNamesAreRefused(): void
    {
        foreach ([
            'Category name cannot be blank' => static fn () => Category::create(CategoryId::generate(), UserId::generate(), '  ', null, new DateTimeImmutable()),
            'Tag name cannot be blank' => static fn () => Tag::create(TagId::generate(), CategoryId::generate(), UserId::generate(), '  ', new DateTimeImmutable()),
        ] as $message => $create) {
            try {
                $create();
                self::fail('Expected InvalidValueException');
            } catch (InvalidValueException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
    }

    public function testATagIsRenamedAndMovedOnlyWhenSomethingChanges(): void
    {
        [$skill, $area] = [CategoryId::generate(), CategoryId::generate()];
        $tag = Tag::fromHistory(Tag::create(TagId::generate(), $skill, UserId::generate(), 'php', new DateTimeImmutable())->releaseEvents());

        $tag->rename(' php ');
        $tag->moveTo($skill);
        self::assertSame([], $tag->releaseEvents());

        $tag->rename('PHP');
        $tag->moveTo($area);

        self::assertSame([TagRenamed::class, TagMoved::class], self::classes($tag->releaseEvents()));
        self::assertTrue($area->equals($tag->categoryId));
        self::assertSame('PHP', $tag->name->value);
    }

    public function testATagReplaysToTheSameState(): void
    {
        $area = CategoryId::generate();
        $tag = Tag::create(TagId::generate(), CategoryId::generate(), UserId::generate(), 'php', new DateTimeImmutable());
        $tag->moveTo($area);
        $tag->archive(new DateTimeImmutable());

        $replayed = Tag::fromHistory($tag->releaseEvents());

        self::assertTrue($area->equals($replayed->categoryId));
        self::assertNotNull($replayed->archivedAt);
        self::assertSame(3, $replayed->version());
    }

    /**
     * @param list<object> $events
     *
     * @return list<class-string>
     */
    private static function classes(array $events): array
    {
        return array_map(static fn (object $e) => $e::class, $events);
    }
}
