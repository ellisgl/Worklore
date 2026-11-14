<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\Company;
use App\Company\CompanyId;
use App\Company\CompanyRepository;
use App\Company\Position;
use App\Company\PositionId;
use App\Company\PositionRepository;
use App\Company\Product;
use App\Company\ProductId;
use App\Company\ProductRepository;
use App\Company\Project;
use App\Company\ProjectId;
use App\Company\ProjectRepository;
use App\Identity\User;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Tests\Functional\CreatesRecords;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The four event-sourced repositories: an owner loads their own records
 * (archived too) from the events; anyone else, or an unknown id, gets null;
 * a stale copy can't be saved.
 */
final class CompanyRepositoriesTest extends KernelTestCase
{
    use CreatesRecords;

    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->me = $this->owner();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function kindProvider(): iterable
    {
        foreach (['company', 'product', 'project', 'position'] as $kind) {
            yield $kind => [$kind];
        }
    }

    #[DataProvider('kindProvider')]
    public function testOwnerLoadsTheirRecordArchivedOrNot(string $kind): void
    {
        $record = $this->record($kind, $this->me);
        $record->archive(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));
        $this->saved($record);

        $loaded = $this->load($record->id(), $this->me);

        self::assertNotNull($loaded);
        self::assertSame('2026-01-01', $loaded->archivedAt?->format('Y-m-d'));
        self::assertSame(2, $loaded->version());
    }

    #[DataProvider('kindProvider')]
    public function testAnotherOwnerOrAnUnknownIdGetsNull(string $kind): void
    {
        $theirs = $this->record($kind, $this->owner('other@example.com'));
        $unknown = match ($kind) {
            'company' => CompanyId::generate(),
            'product' => ProductId::generate(),
            'project' => ProjectId::generate(),
            default => PositionId::generate(),
        };

        self::assertNull($this->load($theirs->id(), $this->me));
        self::assertNull($this->load($unknown, $this->me));
    }

    #[DataProvider('kindProvider')]
    public function testAStaleCopyCannotBeSaved(string $kind): void
    {
        $record = $this->record($kind, $this->me);
        $first = $this->load($record->id(), $this->me);
        $second = $this->load($record->id(), $this->me);
        self::assertNotNull($first);
        self::assertNotNull($second);

        $first->archive(new DateTimeImmutable());
        $this->saved($first);
        $second->archive(new DateTimeImmutable());

        $this->expectException(ConcurrentModification::class);
        $this->saved($second);
    }

    private function record(string $kind, User $owner): Company|Product|Project|Position
    {
        $company = $this->company($owner);

        return match ($kind) {
            'company' => $company,
            'product' => $this->product($company),
            'project' => $this->project($this->product($company)),
            default => $this->position($company),
        };
    }

    private function load(CompanyId|ProductId|ProjectId|PositionId $id, User $owner): Company|Product|Project|Position|null
    {
        $container = self::getContainer();

        return match (true) {
            $id instanceof CompanyId => $container->get(CompanyRepository::class)->findForOwner($id, $owner->id),
            $id instanceof ProductId => $container->get(ProductRepository::class)->findForOwner($id, $owner->id),
            $id instanceof ProjectId => $container->get(ProjectRepository::class)->findForOwner($id, $owner->id),
            default => $container->get(PositionRepository::class)->findForOwner($id, $owner->id),
        };
    }
}
