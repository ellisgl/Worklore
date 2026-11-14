<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Categories and tags become event-sourced: each existing record gets the
 * events that replay to it, and their tables are projections from now on.
 * As with the Company module, the projections drop their keys to other
 * aggregates (tag → category, and the project/journal tag links → tag).
 *
 * Payloads match EventSerializer's output for App\Taxonomy\Event\*;
 * ImportMigrationsTest checks the round trip.
 */
final class Version20261115120000 extends AbstractMigration
{
    private const string TIME = "'YYYY-MM-DD\"T\"HH24:MI:SS\"+00:00\"'";

    public function getDescription(): string
    {
        return 'Categories and tags: import into the event store; drop cross-aggregate keys';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag DROP CONSTRAINT fk_389b78312469de2');
        $this->addSql('ALTER TABLE project_tag DROP CONSTRAINT fk_91f26d60bad26311');
        $this->addSql('ALTER TABLE journal_entry_tag DROP CONSTRAINT fk_42587260bad26311');

        $time = self::TIME;
        $insert = 'INSERT INTO event_store (stream_type, stream_id, stream_version, user_id, event_type, payload, recorded_at)';
        $this->addSql(<<<SQL
            $insert
            SELECT 'category', c.id, 1, c.user_id, 'category.created',
                   jsonb_build_object('category_id', c.id, 'owner_id', c.user_id, 'name', c.name, 'description', c.description, 'created_at', to_char(c.created_at, $time)),
                   c.created_at
              FROM category c ORDER BY c.created_at, c.id
            SQL);
        $this->addSql(<<<SQL
            $insert
            SELECT 'tag', t.id, 1, t.user_id, 'tag.created',
                   jsonb_build_object('tag_id', t.id, 'category_id', t.category_id, 'owner_id', t.user_id, 'name', t.name, 'created_at', to_char(t.created_at, $time)),
                   t.created_at
              FROM tag t ORDER BY t.created_at, t.id
            SQL);
        foreach (['category' => 'c', 'tag' => 't'] as $stream => $alias) {
            $this->addSql(<<<SQL
                $insert
                SELECT '$stream', $alias.id, 2, $alias.user_id, '$stream.archived',
                       jsonb_build_object('{$stream}_id', $alias.id, 'archived_at', to_char($alias.archived_at, $time)),
                       $alias.archived_at
                  FROM $stream $alias WHERE $alias.archived_at IS NOT NULL ORDER BY $alias.archived_at, $alias.id
                SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM event_store WHERE stream_type IN ('category', 'tag')");
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT fk_389b78312469de2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_tag ADD CONSTRAINT fk_91f26d60bad26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE journal_entry_tag ADD CONSTRAINT fk_42587260bad26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }
}
