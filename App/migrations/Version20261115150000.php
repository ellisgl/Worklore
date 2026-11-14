<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Admin impersonation: impersonation tokens name their admin, and every
 * event recorded with one names that admin too. The event column has no
 * foreign key on purpose: the audit trail outlives the admin's account.
 */
final class Version20261115150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Impersonation: api_token.impersonator_id, event_store.impersonator_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_token ADD impersonator_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE api_token ADD CONSTRAINT FK_7BA2F5EBD1107CFF FOREIGN KEY (impersonator_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_7BA2F5EBD1107CFF ON api_token (impersonator_id)');
        $this->addSql('ALTER TABLE event_store ADD impersonator_id UUID DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN event_store.impersonator_id IS 'The admin who recorded the event while impersonating the owner'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_store DROP impersonator_id');
        $this->addSql('ALTER TABLE api_token DROP CONSTRAINT FK_7BA2F5EBD1107CFF');
        $this->addSql('DROP INDEX IDX_7BA2F5EBD1107CFF');
        $this->addSql('ALTER TABLE api_token DROP impersonator_id');
    }
}
