<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250929090500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create facet_config table with uniqueness per category and scope index';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE facet_config (id INT AUTO_INCREMENT NOT NULL, category_id INT DEFAULT NULL, scope VARCHAR(16) NOT NULL, attributes JSON DEFAULT NULL, options JSON DEFAULT NULL, show_zeros TINYINT(1) DEFAULT 0 NOT NULL, collapsed_by_default TINYINT(1) DEFAULT 1 NOT NULL, values_limit INT DEFAULT 20 NOT NULL, values_sort VARCHAR(16) DEFAULT 'popularity' NOT NULL, INDEX IDX_FC_SCOPE (scope), UNIQUE INDEX UNIQ_FC_CATEGORY (category_id), INDEX IDX_FC_CATEGORY (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE facet_config ADD CONSTRAINT FK_FC_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facet_config DROP FOREIGN KEY FK_FC_CATEGORY');
        $this->addSql('DROP TABLE facet_config');
    }
}


