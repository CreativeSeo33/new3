<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250813072709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category ADD slug VARCHAR(255) DEFAULT NULL, ADD visibility TINYINT(1) DEFAULT NULL, ADD parent_category_id INT DEFAULT NULL, ADD meta_title VARCHAR(255) DEFAULT NULL, ADD meta_description VARCHAR(255) DEFAULT NULL, ADD meta_keywords VARCHAR(255) DEFAULT NULL, ADD sort_order INT DEFAULT NULL, ADD meta_h1 VARCHAR(255) DEFAULT NULL, ADD description VARCHAR(255) DEFAULT NULL, ADD navbar_visibility TINYINT(1) DEFAULT 1, ADD footer_visibility TINYINT(1) DEFAULT 1');
        $this->addSql('ALTER TABLE manufacturer RENAME INDEX uniq_manufacturer_name TO UNIQ_3D0AE6DC5E237E06');
        $this->addSql('ALTER TABLE product RENAME INDEX idx_product_manufacturer_id TO IDX_D34A04ADA23B42D');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE manufacturer RENAME INDEX uniq_3d0ae6dc5e237e06 TO UNIQ_MANUFACTURER_NAME');
        $this->addSql('ALTER TABLE category DROP slug, DROP visibility, DROP parent_category_id, DROP meta_title, DROP meta_description, DROP meta_keywords, DROP sort_order, DROP meta_h1, DROP description, DROP navbar_visibility, DROP footer_visibility');
        $this->addSql('ALTER TABLE product RENAME INDEX idx_d34a04ada23b42d TO IDX_PRODUCT_MANUFACTURER_ID');
    }
}
