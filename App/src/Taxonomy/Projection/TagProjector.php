<?php

declare(strict_types=1);

namespace App\Taxonomy\Projection;

use App\Shared\EventSourcing\Projector;
use App\Shared\EventSourcing\StoredEvent;
use App\Shared\EventSourcing\WritesRows;
use App\Shared\Name;
use App\Taxonomy\Event\TagArchived;
use App\Taxonomy\Event\TagCreated;
use App\Taxonomy\Event\TagMoved;
use App\Taxonomy\Event\TagRenamed;
use App\Taxonomy\Event\TagUnarchived;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Projects tag events into tag, the table TagQueries reads.
 */
final readonly class TagProjector implements Projector
{
    use WritesRows;

    public function __construct(private Connection $db)
    {
    }

    public function tables(): array
    {
        return ['tag'];
    }

    public function project(StoredEvent $event): void
    {
        $e = $event->event;
        match (true) {
            $e instanceof TagCreated => $this->db->insert('tag', [
                'id' => $e->tagId->toRfc4122(),
                'category_id' => $e->categoryId->toRfc4122(),
                'user_id' => $e->ownerId->toRfc4122(),
                'name' => $e->name,
                'name_key' => Name::keyOf($e->name),
                'created_at' => self::time($e->createdAt),
                'archived_at' => null,
            ]),
            $e instanceof TagRenamed => $this->update($e->tagId->toRfc4122(), ['name' => $e->name, 'name_key' => Name::keyOf($e->name)]),
            $e instanceof TagMoved => $this->update($e->tagId->toRfc4122(), ['category_id' => $e->categoryId->toRfc4122()]),
            $e instanceof TagArchived => $this->update($e->tagId->toRfc4122(), ['archived_at' => self::time($e->archivedAt)]),
            $e instanceof TagUnarchived => $this->update($e->tagId->toRfc4122(), ['archived_at' => null]),
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
        $this->db->update('tag', $columns, ['id' => $id]);
    }
}
