<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written by hand, like the company/tag name_key migration: add the column,
 * backfill it, then make it required and unique.
 */
final class Version20261114090100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Case-insensitive unique project names within its product (project.name_key)';
    }

    public function preUp(Schema $schema): void
    {
        $duplicates = $this->connection->fetchOne("SELECT string_agg(DISTINCT name, ', ') FROM (SELECT name, count(*) OVER (PARTITION BY product_id, LOWER(TRIM(name))) AS n FROM project) d WHERE n > 1");
        $this->abortIf(
            null !== $duplicates && false !== $duplicates,
            'Some project names are used twice within its product (ignoring case): '.(is_string($duplicates) ? $duplicates : '').'. Rename them, then migrate again.',
        );
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD name_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE project SET name_key = LOWER(TRIM(name))');
        $this->addSql('ALTER TABLE project ALTER name_key SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN project.name_key IS \'Lowercased, trimmed name; makes the unique constraint case-insensitive\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_product_name ON project (product_id, name_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_project_product_name');
        $this->addSql('ALTER TABLE project DROP name_key');
    }
}
