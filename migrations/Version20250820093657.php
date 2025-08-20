<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250820093657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_attribute_assignment (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, attribute_id INT NOT NULL, attribute_group_id INT DEFAULT NULL, data_type VARCHAR(16) DEFAULT \'string\' NOT NULL, string_value VARCHAR(255) DEFAULT NULL, text_value LONGTEXT DEFAULT NULL, int_value INT DEFAULT NULL, decimal_value NUMERIC(15, 4) DEFAULT NULL, bool_value TINYINT(1) DEFAULT NULL, date_value DATE DEFAULT NULL, json_value JSON DEFAULT NULL, unit VARCHAR(32) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, sort_order INT DEFAULT NULL, INDEX IDX_2AFBF05C4584665A (product_id), INDEX IDX_2AFBF05CB6E62EFA (attribute_id), INDEX IDX_2AFBF05C62D643B7 (attribute_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_attribute_assignment ADD CONSTRAINT FK_2AFBF05C4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_attribute_assignment ADD CONSTRAINT FK_2AFBF05CB6E62EFA FOREIGN KEY (attribute_id) REFERENCES attribute (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE product_attribute_assignment ADD CONSTRAINT FK_2AFBF05C62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE attribute_group ADD code VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8EF8A77377153098 ON attribute_group (code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_attribute_assignment DROP FOREIGN KEY FK_2AFBF05C4584665A');
        $this->addSql('ALTER TABLE product_attribute_assignment DROP FOREIGN KEY FK_2AFBF05CB6E62EFA');
        $this->addSql('ALTER TABLE product_attribute_assignment DROP FOREIGN KEY FK_2AFBF05C62D643B7');
        $this->addSql('DROP TABLE product_attribute_assignment');
        $this->addSql('DROP INDEX UNIQ_8EF8A77377153098 ON attribute_group');
        $this->addSql('ALTER TABLE attribute_group DROP code');
    }
}
