<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Journal entries become event-sourced: each existing entry gets the events
 * that replay to it (written, then archived if it is), so the journal_entry
 * tables are a projection from now on. The payloads match EventSerializer's
 * output for App\Journal\Event\*; ImportMigrationsTest checks the round trip.
 */
final class Version20261115100000 extends AbstractMigration
{
    private const string TIME = "'YYYY-MM-DD\"T\"HH24:MI:SS\"+00:00\"'";

    public function getDescription(): string
    {
        return 'Journal entries: import the existing ones into the event store';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            INSERT INTO event_store (stream_type, stream_id, stream_version, user_id, event_type, payload, recorded_at)
            SELECT 'journal_entry', j.id, 1, j.user_id, 'journal_entry.written',
                   jsonb_build_object(
                       'entry_id', j.id,
                       'project_id', j.project_id,
                       'owner_id', j.user_id,
                       'title', j.title,
                       'entry', j.entry,
                       'tag_ids', COALESCE((SELECT jsonb_agg(jt.tag_id ORDER BY jt.tag_id::text) FROM journal_entry_tag jt WHERE jt.journal_entry_id = j.id), '[]'::jsonb),
                       'written_at', to_char(j.created_at, {$this->time()})
                   ),
                   j.created_at
              FROM journal_entry j
             ORDER BY j.created_at, j.id
            SQL);
        $this->addSql(<<<SQL
            INSERT INTO event_store (stream_type, stream_id, stream_version, user_id, event_type, payload, recorded_at)
            SELECT 'journal_entry', j.id, 2, j.user_id, 'journal_entry.archived',
                   jsonb_build_object('entry_id', j.id, 'archived_at', to_char(j.archived_at, {$this->time()})),
                   j.archived_at
              FROM journal_entry j
             WHERE j.archived_at IS NOT NULL
             ORDER BY j.archived_at, j.id
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM event_store WHERE stream_type = 'journal_entry'");
    }

    private function time(): string
    {
        return self::TIME;
    }
}
