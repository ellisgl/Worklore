<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113123443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Companies, positions, products, projects, categories and tags; archived_at on app_user';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_category_archived_at ON category (archived_at)');
        $this->addSql('CREATE INDEX IDX_64C19C1A76ED395 ON category (user_id)');
        $this->addSql('COMMENT ON COLUMN category.name IS \'Name of the category\'');
        $this->addSql('COMMENT ON COLUMN category.description IS \'Description of the category\'');
        $this->addSql('COMMENT ON COLUMN category.created_at IS \'When the record was added\'');
        $this->addSql('COMMENT ON COLUMN category.archived_at IS \'A date time denotes the item was archived\'');
        $this->addSql('CREATE TABLE company (name VARCHAR(255) NOT NULL, overview TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_company_archived_at ON company (archived_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_company_user_name ON company (user_id, name)');
        $this->addSql('CREATE INDEX IDX_4FBF094FA76ED395 ON company (user_id)');
        $this->addSql('COMMENT ON COLUMN company.name IS \'Name of the company\'');
        $this->addSql('COMMENT ON COLUMN company.overview IS \'Overview of the company\'');
        $this->addSql('COMMENT ON COLUMN company.created_at IS \'When the record was added\'');
        $this->addSql('COMMENT ON COLUMN company.archived_at IS \'A date time denotes the item was archived\'');
        $this->addSql('CREATE TABLE position (title VARCHAR(255) NOT NULL, overview TEXT DEFAULT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, reason_left VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, company_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_position_archived_at ON position (archived_at)');
        $this->addSql('CREATE INDEX IDX_462CE4F5979B1AD6 ON position (company_id)');
        $this->addSql('CREATE INDEX IDX_462CE4F5A76ED395 ON position (user_id)');
        $this->addSql('COMMENT ON COLUMN position.title IS \'Title of the position\'');
        $this->addSql('COMMENT ON COLUMN position.overview IS \'Overview of the position\'');
        $this->addSql('COMMENT ON COLUMN position.start_date IS \'When the user started in this position\'');
        $this->addSql('COMMENT ON COLUMN position.end_date IS \'When the position was ended\'');
        $this->addSql('COMMENT ON COLUMN position.reason_left IS \'Why the user left the position\'');
        $this->addSql('COMMENT ON COLUMN position.created_at IS \'When the record was added\'');
        $this->addSql('COMMENT ON COLUMN position.archived_at IS \'A date time denotes the item was archived\'');
        $this->addSql('CREATE TABLE product (name VARCHAR(255) NOT NULL, overview TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, company_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_product_archived_at ON product (archived_at)');
        $this->addSql('CREATE INDEX IDX_D34A04AD979B1AD6 ON product (company_id)');
        $this->addSql('CREATE INDEX IDX_D34A04ADA76ED395 ON product (user_id)');
        $this->addSql('COMMENT ON COLUMN product.name IS \'Name of the product\'');
        $this->addSql('COMMENT ON COLUMN product.overview IS \'Overview of the product\'');
        $this->addSql('COMMENT ON COLUMN product.created_at IS \'When the record was added\'');
        $this->addSql('COMMENT ON COLUMN product.archived_at IS \'A date time denotes the item was archived\'');
        $this->addSql('CREATE TABLE product_tag (product_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY (product_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_E3A6E39C4584665A ON product_tag (product_id)');
        $this->addSql('CREATE INDEX IDX_E3A6E39CBAD26311 ON product_tag (tag_id)');
        $this->addSql('CREATE TABLE project (name VARCHAR(255) NOT NULL, overview TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, product_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_project_archived_at ON project (archived_at)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE4584665A ON project (product_id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEA76ED395 ON project (user_id)');
        $this->addSql('COMMENT ON COLUMN project.name IS \'Name of the project\'');
        $this->addSql('COMMENT ON COLUMN project.overview IS \'Overview of the project\'');
        $this->addSql('COMMENT ON COLUMN project.created_at IS \'When the record was added\'');
        $this->addSql('COMMENT ON COLUMN project.archived_at IS \'A date time denotes the item was archived\'');
        $this->addSql('CREATE TABLE project_tag (project_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY (project_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_91F26D60166D1F9C ON project_tag (project_id)');
        $this->addSql('CREATE INDEX IDX_91F26D60BAD26311 ON project_tag (tag_id)');
        $this->addSql('CREATE TABLE tag (name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, category_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_tag_archived_at ON tag (archived_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_tag_category_name ON tag (category_id, name)');
        $this->addSql('CREATE INDEX IDX_389B78312469DE2 ON tag (category_id)');
        $this->addSql('CREATE INDEX IDX_389B783A76ED395 ON tag (user_id)');
        $this->addSql('COMMENT ON COLUMN tag.name IS \'Name of the tag\'');
        $this->addSql('COMMENT ON COLUMN tag.created_at IS \'When the record was added\'');
        $this->addSql('COMMENT ON COLUMN tag.archived_at IS \'A date time denotes the item was archived\'');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company ADD CONSTRAINT FK_4FBF094FA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE position ADD CONSTRAINT FK_462CE4F5979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE position ADD CONSTRAINT FK_462CE4F5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_tag ADD CONSTRAINT FK_E3A6E39C4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_tag ADD CONSTRAINT FK_E3A6E39CBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_tag ADD CONSTRAINT FK_91F26D60166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_tag ADD CONSTRAINT FK_91F26D60BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_389B78312469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_389B783A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_user ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN app_user.archived_at IS \'A date time denotes the item was archived\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category DROP CONSTRAINT FK_64C19C1A76ED395');
        $this->addSql('ALTER TABLE company DROP CONSTRAINT FK_4FBF094FA76ED395');
        $this->addSql('ALTER TABLE position DROP CONSTRAINT FK_462CE4F5979B1AD6');
        $this->addSql('ALTER TABLE position DROP CONSTRAINT FK_462CE4F5A76ED395');
        $this->addSql('ALTER TABLE product DROP CONSTRAINT FK_D34A04AD979B1AD6');
        $this->addSql('ALTER TABLE product DROP CONSTRAINT FK_D34A04ADA76ED395');
        $this->addSql('ALTER TABLE product_tag DROP CONSTRAINT FK_E3A6E39C4584665A');
        $this->addSql('ALTER TABLE product_tag DROP CONSTRAINT FK_E3A6E39CBAD26311');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EE4584665A');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EEA76ED395');
        $this->addSql('ALTER TABLE project_tag DROP CONSTRAINT FK_91F26D60166D1F9C');
        $this->addSql('ALTER TABLE project_tag DROP CONSTRAINT FK_91F26D60BAD26311');
        $this->addSql('ALTER TABLE tag DROP CONSTRAINT FK_389B78312469DE2');
        $this->addSql('ALTER TABLE tag DROP CONSTRAINT FK_389B783A76ED395');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE position');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_tag');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE project_tag');
        $this->addSql('DROP TABLE tag');
        $this->addSql('ALTER TABLE app_user DROP archived_at');
    }
}
