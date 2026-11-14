<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * journal_media: the projection of files attached to journal entries (the
 * events are in the journal entry streams; the bytes in media storage).
 * Like the other projections it is outside the ORM mapping.
 */
final class Version20261115140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal entry media projection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE journal_media (
                id UUID NOT NULL,
                journal_entry_id UUID NOT NULL,
                user_id UUID NOT NULL,
                filename VARCHAR(255) NOT NULL,
                media_type VARCHAR(255) NOT NULL,
                size BIGINT NOT NULL,
                sha256 CHAR(64) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_journal_media_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
            )
            SQL);
        $this->addSql('CREATE INDEX idx_journal_media_entry ON journal_media (journal_entry_id)');
        $this->addSql('CREATE INDEX idx_journal_media_user ON journal_media (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE journal_media');
    }
}
