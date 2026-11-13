<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113161250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal entries and their tags; index on position.start_date';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE journal_entry (title VARCHAR(255) NOT NULL, entry TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, user_id UUID NOT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_journal_entry_archived_at ON journal_entry (archived_at)');
        $this->addSql('CREATE INDEX IDX_C8FAAE5AA76ED395 ON journal_entry (user_id)');
        $this->addSql('CREATE INDEX IDX_C8FAAE5A166D1F9C ON journal_entry (project_id)');
        $this->addSql('COMMENT ON COLUMN journal_entry.title IS \'The title of the entry\'');
        $this->addSql('COMMENT ON COLUMN journal_entry.entry IS \'The content body of the entry.\'');
        $this->addSql('COMMENT ON COLUMN journal_entry.created_at IS \'When the record was added\'');
        $this->addSql('COMMENT ON COLUMN journal_entry.archived_at IS \'A date time denotes the item was archived\'');
        $this->addSql('CREATE TABLE journal_entry_tag (journal_entry_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY (journal_entry_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_425872606A86E4FB ON journal_entry_tag (journal_entry_id)');
        $this->addSql('CREATE INDEX IDX_42587260BAD26311 ON journal_entry_tag (tag_id)');
        $this->addSql('ALTER TABLE journal_entry ADD CONSTRAINT FK_C8FAAE5AA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE journal_entry ADD CONSTRAINT FK_C8FAAE5A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE journal_entry_tag ADD CONSTRAINT FK_425872606A86E4FB FOREIGN KEY (journal_entry_id) REFERENCES journal_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE journal_entry_tag ADD CONSTRAINT FK_42587260BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_position_start_date ON "position" (start_date)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE journal_entry DROP CONSTRAINT FK_C8FAAE5AA76ED395');
        $this->addSql('ALTER TABLE journal_entry DROP CONSTRAINT FK_C8FAAE5A166D1F9C');
        $this->addSql('ALTER TABLE journal_entry_tag DROP CONSTRAINT FK_425872606A86E4FB');
        $this->addSql('ALTER TABLE journal_entry_tag DROP CONSTRAINT FK_42587260BAD26311');
        $this->addSql('DROP TABLE journal_entry');
        $this->addSql('DROP TABLE journal_entry_tag');
        $this->addSql('DROP INDEX idx_position_start_date');
    }
}
