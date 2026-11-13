<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written by hand, like the company/tag name_key migration: add the column,
 * backfill it, then make it required and unique.
 */
final class Version20261114090200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Case-insensitive unique category names per user (category.name_key)';
    }

    public function preUp(Schema $schema): void
    {
        $duplicates = $this->connection->fetchOne("SELECT string_agg(DISTINCT name, ', ') FROM (SELECT name, count(*) OVER (PARTITION BY user_id, LOWER(TRIM(name))) AS n FROM category) d WHERE n > 1");
        $this->abortIf(
            null !== $duplicates && false !== $duplicates,
            'Some category names are used twice per user (ignoring case): '.(is_string($duplicates) ? $duplicates : '').'. Rename them, then migrate again.',
        );
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD name_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE category SET name_key = LOWER(TRIM(name))');
        $this->addSql('ALTER TABLE category ALTER name_key SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN category.name_key IS \'Lowercased, trimmed name; makes the unique constraint case-insensitive\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_category_user_name ON category (user_id, name_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_category_user_name');
        $this->addSql('ALTER TABLE category DROP name_key');
    }
}
