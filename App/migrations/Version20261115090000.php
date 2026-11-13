<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written by hand; event_store is outside Doctrine's mapping (schema_filter).
 */
final class Version20261115090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Event store: the append-only log of domain events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE event_store (
                sequence BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                stream_type VARCHAR(50) NOT NULL,
                stream_id UUID NOT NULL,
                stream_version INT NOT NULL,
                user_id UUID NOT NULL REFERENCES app_user (id) ON DELETE CASCADE,
                event_type VARCHAR(100) NOT NULL,
                payload JSONB NOT NULL,
                recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT uniq_event_store_stream_version UNIQUE (stream_id, stream_version)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_event_store_user ON event_store (user_id, sequence)');
        $this->addSql("COMMENT ON TABLE event_store IS 'Every domain event, one stream per aggregate; the source of truth for event-sourced modules'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_store');
    }
}
