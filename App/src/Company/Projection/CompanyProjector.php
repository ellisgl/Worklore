<?php

declare(strict_types=1);

namespace App\Company\Projection;

use App\Company\CompanyEvents;
use App\Company\Event\CompanyArchived;
use App\Company\Event\CompanyCreated;
use App\Company\Event\CompanyOverviewChanged;
use App\Company\Event\CompanyRenamed;
use App\Company\Event\CompanyUnarchived;
use App\Shared\EventSourcing\Projector;
use App\Shared\EventSourcing\StoredEvent;
use App\Shared\EventSourcing\WritesRows;
use App\Shared\Name;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Projects company events into company, the tables CompanyQueries reads.
 */
final readonly class CompanyProjector implements Projector
{
    use WritesRows;

    public function __construct(private Connection $db)
    {
    }

    public function tables(): array
    {
        return ['company'];
    }

    public function forget(string $streamType, array $ids): void
    {
        if (CompanyEvents::COMPANY === $streamType) {
            self::deleteIn($this->db, 'company', 'id', $ids);
        }
    }

    public function project(StoredEvent $event): void
    {
        $e = $event->event;
        match (true) {
            $e instanceof CompanyCreated => $this->created($e),
            $e instanceof CompanyRenamed => $this->update($e->companyId->toRfc4122(), ['name' => $e->name, 'name_key' => Name::keyOf($e->name)]),
            $e instanceof CompanyOverviewChanged => $this->update($e->companyId->toRfc4122(), ['overview' => $e->overview]),
            $e instanceof CompanyArchived => $this->update($e->companyId->toRfc4122(), ['archived_at' => self::time($e->archivedAt)]),
            $e instanceof CompanyUnarchived => $this->update($e->companyId->toRfc4122(), ['archived_at' => null]),
            default => null,
        };
    }

    /**
     * @throws Exception
     */
    private function created(CompanyCreated $e): void
    {
        $this->db->insert('company', [
            'id' => $e->companyId->toRfc4122(),
            'user_id' => $e->ownerId->toRfc4122(),
            'name' => $e->name,
            'name_key' => Name::keyOf($e->name),
            'overview' => $e->overview,
            'created_at' => self::time($e->createdAt),
            'archived_at' => null,
        ]);
    }

    /**
     * @param array<string, string|null> $columns
     *
     * @throws Exception
     */
    private function update(string $id, array $columns): void
    {
        $this->db->update('company', $columns, ['id' => $id]);
    }
}
