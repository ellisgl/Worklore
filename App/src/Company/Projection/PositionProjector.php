<?php

declare(strict_types=1);

namespace App\Company\Projection;

use App\Company\Event\PositionArchived;
use App\Company\Event\PositionCreated;
use App\Company\Event\PositionEnded;
use App\Company\Event\PositionOverviewChanged;
use App\Company\Event\PositionReopened;
use App\Company\Event\PositionRetitled;
use App\Company\Event\PositionStartDateChanged;
use App\Company\Event\PositionUnarchived;
use App\Shared\EventSourcing\Projector;
use App\Shared\EventSourcing\StoredEvent;
use App\Shared\EventSourcing\WritesRows;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Projects position events into "position", the table PositionQueries reads.
 */
final readonly class PositionProjector implements Projector
{
    use WritesRows;

    public function __construct(private Connection $db)
    {
    }

    public function tables(): array
    {
        return ['"position"'];
    }

    public function project(StoredEvent $event): void
    {
        $e = $event->event;
        match (true) {
            $e instanceof PositionCreated => $this->created($e),
            $e instanceof PositionRetitled => $this->update($e->positionId->toRfc4122(), ['title' => $e->title]),
            $e instanceof PositionOverviewChanged => $this->update($e->positionId->toRfc4122(), ['overview' => $e->overview]),
            $e instanceof PositionStartDateChanged => $this->update($e->positionId->toRfc4122(), ['start_date' => self::date($e->startDate)]),
            $e instanceof PositionEnded => $this->update($e->positionId->toRfc4122(), ['end_date' => self::date($e->endDate), 'reason_left' => $e->reasonLeft]),
            $e instanceof PositionReopened => $this->update($e->positionId->toRfc4122(), ['end_date' => null, 'reason_left' => null]),
            $e instanceof PositionArchived => $this->update($e->positionId->toRfc4122(), ['archived_at' => self::time($e->archivedAt)]),
            $e instanceof PositionUnarchived => $this->update($e->positionId->toRfc4122(), ['archived_at' => null]),
            default => null,
        };
    }

    /**
     * @throws Exception
     */
    private function created(PositionCreated $e): void
    {
        $this->db->insert('"position"', [
            'id' => $e->positionId->toRfc4122(),
            'company_id' => $e->companyId->toRfc4122(),
            'user_id' => $e->ownerId->toRfc4122(),
            'title' => $e->title,
            'overview' => $e->overview,
            'start_date' => self::date($e->startDate),
            'end_date' => null,
            'reason_left' => null,
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
        $this->db->update('"position"', $columns, ['id' => $id]);
    }
}
