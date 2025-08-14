<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250814075234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attribute (id INT AUTO_INCREMENT NOT NULL, attribute_group_id INT DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, sort_order INT DEFAULT NULL, show_in_category TINYINT(1) DEFAULT NULL, short_name VARCHAR(255) DEFAULT NULL, INDEX IDX_FA7AEFFB62D643B7 (attribute_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE attribute_group (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, sort_order INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE carousel (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, products_id JSON DEFAULT NULL, sort INT DEFAULT NULL, place VARCHAR(255) DEFAULT NULL, INDEX IDX_1DD747004584665A (product_id), INDEX carousel_place_idx (place), INDEX carousel_sort_idx (sort), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, visibility TINYINT(1) DEFAULT NULL, parent_category_id INT DEFAULT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description VARCHAR(255) DEFAULT NULL, meta_keywords VARCHAR(255) DEFAULT NULL, sort_order INT DEFAULT NULL, meta_h1 VARCHAR(255) DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, navbar_visibility TINYINT(1) DEFAULT 1, footer_visibility TINYINT(1) DEFAULT 1, INDEX category_name_idx (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE manufacturer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_3D0AE6DC5E237E06 (name), INDEX manufacturer_name_idx (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `option` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, sort_order INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE option_value (id INT AUTO_INCREMENT NOT NULL, option_type_id INT DEFAULT NULL, value VARCHAR(255) NOT NULL, sort_order INT NOT NULL, INDEX IDX_249CE55CDDB89BE6 (option_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, manufacturer_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) DEFAULT NULL, sort_order INT DEFAULT NULL, effective_price INT DEFAULT NULL, status TINYINT(1) DEFAULT 0 NOT NULL, quantity INT DEFAULT NULL, options_json JSON DEFAULT NULL, attribute_json JSON DEFAULT NULL, code BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:ulid)\', description VARCHAR(255) DEFAULT NULL, price INT DEFAULT NULL, sale_price INT DEFAULT NULL, currency VARCHAR(3) DEFAULT \'RUB\' NOT NULL, date_added DATETIME DEFAULT NULL, date_edited DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_D34A04AD989D9B62 (slug), UNIQUE INDEX UNIQ_D34A04AD77153098 (code), INDEX IDX_D34A04ADA23B42D (manufacturer_id), INDEX name (name), INDEX product_status_idx (status), INDEX product_date_added_idx (date_added), INDEX product_sort_order_idx (sort_order), INDEX idx_product_status_created (status, date_added), INDEX product_effective_price_idx (effective_price), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_attribute (id INT AUTO_INCREMENT NOT NULL, product_attribute_group_id INT DEFAULT NULL, attribute_id INT DEFAULT NULL, text VARCHAR(255) DEFAULT NULL, INDEX IDX_94DA59769000C6CB (product_attribute_group_id), INDEX IDX_94DA5976B6E62EFA (attribute_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_attribute_group (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, attribute_group_id INT DEFAULT NULL, INDEX IDX_BC73A2EE4584665A (product_id), INDEX IDX_BC73A2EE62D643B7 (attribute_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_image (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, image_url VARCHAR(255) NOT NULL, sort_order INT NOT NULL, INDEX product_id (product_id), INDEX product_image_sort_idx (sort_order), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_seo (product_id INT NOT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description VARCHAR(255) DEFAULT NULL, meta_keywords VARCHAR(255) DEFAULT NULL, h1 VARCHAR(255) DEFAULT NULL, PRIMARY KEY(product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_to_category (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, category_id INT DEFAULT NULL, is_parent TINYINT(1) DEFAULT 0, position INT DEFAULT 1, visibility TINYINT(1) DEFAULT 1, INDEX IDX_673A19704584665A (product_id), INDEX IDX_673A197012469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_1483A5E95E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFB62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE carousel ADD CONSTRAINT FK_1DD747004584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE option_value ADD CONSTRAINT FK_249CE55CDDB89BE6 FOREIGN KEY (option_type_id) REFERENCES `option` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADA23B42D FOREIGN KEY (manufacturer_id) REFERENCES manufacturer (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT FK_94DA59769000C6CB FOREIGN KEY (product_attribute_group_id) REFERENCES product_attribute_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT FK_94DA5976B6E62EFA FOREIGN KEY (attribute_id) REFERENCES attribute (id)');
        $this->addSql('ALTER TABLE product_attribute_group ADD CONSTRAINT FK_BC73A2EE4584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE product_attribute_group ADD CONSTRAINT FK_BC73A2EE62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F034584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_seo ADD CONSTRAINT FK_8C5EB82F4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_to_category ADD CONSTRAINT FK_673A19704584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_to_category ADD CONSTRAINT FK_673A197012469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute DROP FOREIGN KEY FK_FA7AEFFB62D643B7');
        $this->addSql('ALTER TABLE carousel DROP FOREIGN KEY FK_1DD747004584665A');
        $this->addSql('ALTER TABLE option_value DROP FOREIGN KEY FK_249CE55CDDB89BE6');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADA23B42D');
        $this->addSql('ALTER TABLE product_attribute DROP FOREIGN KEY FK_94DA59769000C6CB');
        $this->addSql('ALTER TABLE product_attribute DROP FOREIGN KEY FK_94DA5976B6E62EFA');
        $this->addSql('ALTER TABLE product_attribute_group DROP FOREIGN KEY FK_BC73A2EE4584665A');
        $this->addSql('ALTER TABLE product_attribute_group DROP FOREIGN KEY FK_BC73A2EE62D643B7');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F034584665A');
        $this->addSql('ALTER TABLE product_seo DROP FOREIGN KEY FK_8C5EB82F4584665A');
        $this->addSql('ALTER TABLE product_to_category DROP FOREIGN KEY FK_673A19704584665A');
        $this->addSql('ALTER TABLE product_to_category DROP FOREIGN KEY FK_673A197012469DE2');
        $this->addSql('DROP TABLE attribute');
        $this->addSql('DROP TABLE attribute_group');
        $this->addSql('DROP TABLE carousel');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE manufacturer');
        $this->addSql('DROP TABLE `option`');
        $this->addSql('DROP TABLE option_value');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_attribute');
        $this->addSql('DROP TABLE product_attribute_group');
        $this->addSql('DROP TABLE product_image');
        $this->addSql('DROP TABLE product_seo');
        $this->addSql('DROP TABLE product_to_category');
        $this->addSql('DROP TABLE users');
    }
}
