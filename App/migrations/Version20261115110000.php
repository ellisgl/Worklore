<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Companies, products, projects and positions become event-sourced: each
 * existing record gets the events that replay to it, and their tables are
 * projections from now on.
 *
 * Projections don't keep foreign keys between aggregates: replaying events
 * fills them in event order, which needn't match parent-before-child (the
 * journal entries imported earlier come before their projects). Keys within
 * an aggregate (project_tag) and to app_user stay. product_tag is dropped:
 * nothing ever set product tags.
 *
 * Payloads match EventSerializer's output for App\Company\Event\*;
 * ImportMigrationsTest checks the round trip.
 */
final class Version20261115110000 extends AbstractMigration
{
    private const string TIME = "'YYYY-MM-DD\"T\"HH24:MI:SS\"+00:00\"'";
    private const string DAY = "'YYYY-MM-DD\"T00:00:00+00:00\"'";

    public function getDescription(): string
    {
        return 'Companies, products, projects and positions: import into the event store; drop cross-aggregate keys and product_tag';
    }

    public function preUp(Schema $schema): void
    {
        $this->abortIf(
            0 !== (int) $this->connection->fetchOne('SELECT count(*) FROM product_tag'),
            'product_tag has rows, but product tags are being removed; move or delete them first.',
        );
    }

    public function up(Schema $schema): void
    {
        foreach ([
            'product' => 'fk_d34a04ad979b1ad6',
            'project' => 'fk_2fb3d0ee4584665a',
            '"position"' => 'fk_462ce4f5979b1ad6',
            'journal_entry' => 'fk_c8faae5a166d1f9c',
        ] as $table => $key) {
            $this->addSql("ALTER TABLE $table DROP CONSTRAINT $key");
        }
        $this->addSql('DROP TABLE product_tag');

        $time = self::TIME;
        $day = self::DAY;
        $insert = 'INSERT INTO event_store (stream_type, stream_id, stream_version, user_id, event_type, payload, recorded_at)';

        $this->addSql(<<<SQL
            $insert
            SELECT 'company', c.id, 1, c.user_id, 'company.created',
                   jsonb_build_object('company_id', c.id, 'owner_id', c.user_id, 'name', c.name, 'overview', c.overview, 'created_at', to_char(c.created_at, $time)),
                   c.created_at
              FROM company c ORDER BY c.created_at, c.id
            SQL);
        $this->addSql(<<<SQL
            $insert
            SELECT 'product', p.id, 1, p.user_id, 'product.created',
                   jsonb_build_object('product_id', p.id, 'company_id', p.company_id, 'owner_id', p.user_id, 'name', p.name, 'overview', p.overview, 'created_at', to_char(p.created_at, $time)),
                   p.created_at
              FROM product p ORDER BY p.created_at, p.id
            SQL);
        $this->addSql(<<<SQL
            $insert
            SELECT 'project', pj.id, 1, pj.user_id, 'project.created',
                   jsonb_build_object(
                       'project_id', pj.id, 'product_id', pj.product_id, 'owner_id', pj.user_id, 'name', pj.name, 'overview', pj.overview,
                       'tag_ids', COALESCE((SELECT jsonb_agg(pt.tag_id ORDER BY pt.tag_id::text) FROM project_tag pt WHERE pt.project_id = pj.id), '[]'::jsonb),
                       'created_at', to_char(pj.created_at, $time)
                   ),
                   pj.created_at
              FROM project pj ORDER BY pj.created_at, pj.id
            SQL);
        $this->addSql(<<<SQL
            $insert
            SELECT 'position', po.id, 1, po.user_id, 'position.created',
                   jsonb_build_object('position_id', po.id, 'company_id', po.company_id, 'owner_id', po.user_id, 'title', po.title, 'overview', po.overview,
                                      'start_date', to_char(po.start_date, $day), 'created_at', to_char(po.created_at, $time)),
                   po.created_at
              FROM "position" po ORDER BY po.created_at, po.id
            SQL);
        $this->addSql(<<<SQL
            $insert
            SELECT 'position', po.id, 2, po.user_id, 'position.ended',
                   jsonb_build_object('position_id', po.id, 'end_date', to_char(po.end_date, $day), 'reason_left', po.reason_left),
                   po.created_at
              FROM "position" po WHERE po.end_date IS NOT NULL ORDER BY po.created_at, po.id
            SQL);

        // Archived last: version 2, or 3 for a position that also ended
        foreach (['company' => ['company', 'c', '2'], 'product' => ['product', 'p', '2'], 'project' => ['project', 'pj', '2'], 'position' => ['"position"', 'po', 'CASE WHEN po.end_date IS NULL THEN 2 ELSE 3 END']] as $stream => [$table, $alias, $version]) {
            $this->addSql(<<<SQL
                $insert
                SELECT '$stream', $alias.id, $version, $alias.user_id, '$stream.archived',
                       jsonb_build_object('{$stream}_id', $alias.id, 'archived_at', to_char($alias.archived_at, $time)),
                       $alias.archived_at
                  FROM $table $alias WHERE $alias.archived_at IS NOT NULL ORDER BY $alias.archived_at, $alias.id
                SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM event_store WHERE stream_type IN ('company', 'product', 'project', 'position')");
        $this->addSql('CREATE TABLE product_tag (product_id UUID NOT NULL REFERENCES product (id) ON DELETE CASCADE, tag_id UUID NOT NULL REFERENCES tag (id) ON DELETE CASCADE, PRIMARY KEY (product_id, tag_id))');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT fk_d34a04ad979b1ad6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT fk_2fb3d0ee4584665a FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE "position" ADD CONSTRAINT fk_462ce4f5979b1ad6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE journal_entry ADD CONSTRAINT fk_c8faae5a166d1f9c FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
    }
}
