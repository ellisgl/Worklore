<?php

declare(strict_types=1);

namespace App\Taxonomy\Projection;

use App\Shared\EventSourcing\Projector;
use App\Shared\EventSourcing\StoredEvent;
use App\Shared\EventSourcing\WritesRows;
use App\Shared\Name;
use App\Taxonomy\Event\CategoryArchived;
use App\Taxonomy\Event\CategoryCreated;
use App\Taxonomy\Event\CategoryDescriptionChanged;
use App\Taxonomy\Event\CategoryRenamed;
use App\Taxonomy\Event\CategoryUnarchived;
use App\Taxonomy\TaxonomyEvents;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Projects category events into category, the table CategoryQueries reads.
 */
final readonly class CategoryProjector implements Projector
{
    use WritesRows;

    public function __construct(private Connection $db)
    {
    }

    public function tables(): array
    {
        return ['category'];
    }

    public function forget(string $streamType, array $ids): void
    {
        if (TaxonomyEvents::CATEGORY === $streamType) {
            self::deleteIn($this->db, 'category', 'id', $ids);
        }
    }

    public function project(StoredEvent $event): void
    {
        $e = $event->event;
        match (true) {
            $e instanceof CategoryCreated => $this->db->insert('category', [
                'id' => $e->categoryId->toRfc4122(),
                'user_id' => $e->ownerId->toRfc4122(),
                'name' => $e->name,
                'name_key' => Name::keyOf($e->name),
                'description' => $e->description,
                'created_at' => self::time($e->createdAt),
                'archived_at' => null,
            ]),
            $e instanceof CategoryRenamed => $this->update($e->categoryId->toRfc4122(), ['name' => $e->name, 'name_key' => Name::keyOf($e->name)]),
            $e instanceof CategoryDescriptionChanged => $this->update($e->categoryId->toRfc4122(), ['description' => $e->description]),
            $e instanceof CategoryArchived => $this->update($e->categoryId->toRfc4122(), ['archived_at' => self::time($e->archivedAt)]),
            $e instanceof CategoryUnarchived => $this->update($e->categoryId->toRfc4122(), ['archived_at' => null]),
            default => null,
        };
    }

    /**
     * @param array<string, string|null> $columns
     *
     * @throws Exception
     */
    private function update(string $id, array $columns): void
    {
        $this->db->update('category', $columns, ['id' => $id]);
    }
}
