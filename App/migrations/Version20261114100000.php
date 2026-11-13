<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written by hand: an expression index can't be mapped, and Doctrine's schema
 * tools ignore it (they don't try to drop it). The expression must match
 * JournalEntryQueries::SEARCH for PostgreSQL to use it.
 */
final class Version20261114100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Full-text search index on journal entries (title and text)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE INDEX idx_journal_entry_search ON journal_entry USING GIN (to_tsvector('english', title || ' ' || entry))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_journal_entry_search');
    }
}
