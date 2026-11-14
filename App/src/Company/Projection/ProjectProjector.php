<?php

declare(strict_types=1);

namespace App\Company\Projection;

use App\Company\Event\ProjectArchived;
use App\Company\Event\ProjectCreated;
use App\Company\Event\ProjectOverviewChanged;
use App\Company\Event\ProjectRenamed;
use App\Company\Event\ProjectRetagged;
use App\Company\Event\ProjectUnarchived;
use App\Shared\EventSourcing\Projector;
use App\Shared\EventSourcing\StoredEvent;
use App\Shared\EventSourcing\WritesRows;
use App\Shared\Name;
use App\Taxonomy\TagId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Projects project events into project and project_tag, the tables ProjectQueries reads.
 */
final readonly class ProjectProjector implements Projector
{
    use WritesRows;

    public function __construct(private Connection $db)
    {
    }

    public function tables(): array
    {
        return ['project_tag', 'project'];
    }

    public function project(StoredEvent $event): void
    {
        $e = $event->event;
        match (true) {
            $e instanceof ProjectCreated => $this->created($e),
            $e instanceof ProjectRenamed => $this->update($e->projectId->toRfc4122(), ['name' => $e->name, 'name_key' => Name::keyOf($e->name)]),
            $e instanceof ProjectOverviewChanged => $this->update($e->projectId->toRfc4122(), ['overview' => $e->overview]),
            $e instanceof ProjectRetagged => $this->tag($e->projectId->toRfc4122(), $e->tagIds),
            $e instanceof ProjectArchived => $this->update($e->projectId->toRfc4122(), ['archived_at' => self::time($e->archivedAt)]),
            $e instanceof ProjectUnarchived => $this->update($e->projectId->toRfc4122(), ['archived_at' => null]),
            default => null,
        };
    }

    /**
     * @throws Exception
     */
    private function created(ProjectCreated $e): void
    {
        $this->db->insert('project', [
            'id' => $e->projectId->toRfc4122(),
            'product_id' => $e->productId->toRfc4122(),
            'user_id' => $e->ownerId->toRfc4122(),
            'name' => $e->name,
            'name_key' => Name::keyOf($e->name),
            'overview' => $e->overview,
            'created_at' => self::time($e->createdAt),
            'archived_at' => null,
        ]);
        $this->tag($e->projectId->toRfc4122(), $e->tagIds);
    }

    /**
     * @param array<string, string|null> $columns
     *
     * @throws Exception
     */
    private function update(string $id, array $columns): void
    {
        $this->db->update('project', $columns, ['id' => $id]);
    }

    /**
     * @param list<TagId> $tagIds
     *
     * @throws Exception
     */
    private function tag(string $id, array $tagIds): void
    {
        $this->db->delete('project_tag', ['project_id' => $id]);
        foreach ($tagIds as $tagId) {
            $this->db->insert('project_tag', ['project_id' => $id, 'tag_id' => $tagId->toRfc4122()]);
        }
    }
}
