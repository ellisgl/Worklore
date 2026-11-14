<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-user media quota (null: MEDIA_USER_QUOTA)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD storage_quota BIGINT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN app_user.storage_quota IS \'Bytes of media the user may store; null: MEDIA_USER_QUOTA\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP storage_quota');
    }
}
