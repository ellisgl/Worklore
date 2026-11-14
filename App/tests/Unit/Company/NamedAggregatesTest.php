<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company;

use App\Company\Company;
use App\Company\CompanyId;
use App\Company\Event\CompanyArchived;
use App\Company\Event\CompanyCreated;
use App\Company\Event\CompanyOverviewChanged;
use App\Company\Event\CompanyRenamed;
use App\Company\Event\CompanyUnarchived;
use App\Company\Event\ProjectRetagged;
use App\Company\Product;
use App\Company\ProductId;
use App\Company\Project;
use App\Company\ProjectId;
use App\Shared\InvalidValueException;
use App\Shared\Name;
use App\Shared\UserId;
use App\Taxonomy\TagId;
use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function str_repeat;

/**
 * Company, Product and Project share a lifecycle: created with a name and an
 * optional overview, renamed, described, archived. Given their history, when
 * told to change, then they record these events (or refuse).
 */
final class NamedAggregatesTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(string, ?string): (Company|Product|Project), string}>
     */
    public static function aggregateProvider(): iterable
    {
        $at = new DateTimeImmutable('2026-03-04 05:06:07 UTC');
        yield 'company' => [static fn (string $name, ?string $overview) => Company::create(CompanyId::generate(), UserId::generate(), $name, $overview, $at), 'Company name'];
        yield 'product' => [static fn (string $name, ?string $overview) => Product::create(ProductId::generate(), CompanyId::generate(), UserId::generate(), $name, $overview, $at), 'Product name'];
        yield 'project' => [static fn (string $name, ?string $overview) => Project::create(ProjectId::generate(), ProductId::generate(), UserId::generate(), $name, $overview, [], $at), 'Project name'];
    }

    /**
     * @param Closure(string, ?string): (Company|Product|Project) $create
     */
    #[DataProvider('aggregateProvider')]
    public function testCreationTrimsTheNameAndOverview(Closure $create, string $label): void
    {
        $record = $create('  Acme  ', "  \n ");

        self::assertSame('Acme', $record->name->value);
        self::assertNull($record->overview, 'A blank overview is none');
        self::assertCount(1, $record->releaseEvents());
    }

    /**
     * @param Closure(string, ?string): (Company|Product|Project) $create
     */
    #[DataProvider('aggregateProvider')]
    public function testBlankOrTooLongNamesAreRefused(Closure $create, string $label): void
    {
        foreach (['   ' => "$label cannot be blank", str_repeat('x', Name::MAX_LENGTH + 1) => null] as $name => $message) {
            try {
                $create((string) $name, null);
                self::fail('Expected InvalidValueException');
            } catch (InvalidValueException $e) {
                if (null !== $message) {
                    self::assertSame($message, $e->getMessage());
                }
            }
        }
        self::assertSame(str_repeat('x', Name::MAX_LENGTH), $create(str_repeat('x', Name::MAX_LENGTH), null)->name->value, 'The maximum itself is fine');
    }

    /**
     * @param Closure(string, ?string): (Company|Product|Project) $create
     */
    #[DataProvider('aggregateProvider')]
    public function testChangesRecordOnlyWhatChanged(Closure $create, string $label): void
    {
        $record = $create('Acme', 'Widgets');
        $record->releaseEvents();

        $record->rename(' Acme ');          // same name: nothing
        $record->describe('Widgets');       // same overview: nothing
        $record->unarchive();               // not archived: nothing
        self::assertSame([], $record->releaseEvents());

        $record->rename('ACME');            // a different case is a change
        $record->describe('');              // blank clears it
        $record->archive(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));
        $record->archive(new DateTimeImmutable('2026-02-01 00:00:00 UTC'));   // keeps the first
        self::assertCount(3, $record->releaseEvents());
        self::assertSame('ACME', $record->name->value);
        self::assertNull($record->overview);
        self::assertSame('2026-01-01', $record->archivedAt?->format('Y-m-d'));
    }

    public function testACompanyReplaysToTheSameState(): void
    {
        $id = CompanyId::generate();
        $company = Company::fromHistory([
            new CompanyCreated($id, UserId::generate(), 'Acme', null, new DateTimeImmutable()),
            new CompanyRenamed($id, 'Acme Ltd'),
            new CompanyOverviewChanged($id, 'Widgets'),
            new CompanyArchived($id, new DateTimeImmutable('2026-01-01 00:00:00 UTC')),
            new CompanyUnarchived($id),
        ]);

        self::assertSame('Acme Ltd', $company->name->value);
        self::assertSame('Widgets', $company->overview);
        self::assertFalse($company->isArchived());
        self::assertSame(5, $company->version());
        self::assertSame([], $company->releaseEvents());
    }

    public function testAProjectKeepsDistinctSortedTagsAndRecordsRealChangesOnly(): void
    {
        [$a, $b] = [TagId::generate(), TagId::generate()];
        $project = Project::create(ProjectId::generate(), ProductId::generate(), UserId::generate(), 'Launch', null, [$b, $a, $b], new DateTimeImmutable());
        $project->releaseEvents();

        $project->retag([$a, $b]);          // same set: nothing
        self::assertSame([], $project->releaseEvents());

        $project->retag([$b]);
        $events = $project->releaseEvents();
        self::assertInstanceOf(ProjectRetagged::class, $events[0]);
        self::assertSame([$b->toRfc4122()], array_map(static fn (TagId $t) => $t->toRfc4122(), $project->tagIds));
    }
}
