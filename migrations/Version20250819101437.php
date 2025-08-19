<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250819101437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_option_value_assignment (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, option_id INT NOT NULL, value_id INT NOT NULL, height INT DEFAULT NULL, price INT DEFAULT NULL, bulbs_count INT DEFAULT NULL, lighting_area INT DEFAULT NULL, sku VARCHAR(64) DEFAULT NULL, attributes JSON DEFAULT NULL, INDEX IDX_E85761E84584665A (product_id), INDEX IDX_E85761E8A7C41D6F (option_id), INDEX IDX_E85761E8F920BBA2 (value_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_option_value_assignment ADD CONSTRAINT FK_E85761E84584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_option_value_assignment ADD CONSTRAINT FK_E85761E8A7C41D6F FOREIGN KEY (option_id) REFERENCES `option` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_option_value_assignment ADD CONSTRAINT FK_E85761E8F920BBA2 FOREIGN KEY (value_id) REFERENCES option_value (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `option` ADD code VARCHAR(100) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8600B077153098 ON `option` (code)');
        $this->addSql('ALTER TABLE option_value DROP FOREIGN KEY FK_249CE55CDDB89BE6');
        $this->addSql('DROP INDEX IDX_249CE55CDDB89BE6 ON option_value');
        $this->addSql('ALTER TABLE option_value ADD code VARCHAR(100) NOT NULL, CHANGE option_type_id option_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE option_value ADD CONSTRAINT FK_249CE55CA7C41D6F FOREIGN KEY (option_id) REFERENCES `option` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_249CE55CA7C41D6F ON option_value (option_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_option_value_assignment DROP FOREIGN KEY FK_E85761E84584665A');
        $this->addSql('ALTER TABLE product_option_value_assignment DROP FOREIGN KEY FK_E85761E8A7C41D6F');
        $this->addSql('ALTER TABLE product_option_value_assignment DROP FOREIGN KEY FK_E85761E8F920BBA2');
        $this->addSql('DROP TABLE product_option_value_assignment');
        $this->addSql('DROP INDEX UNIQ_5A8600B077153098 ON `option`');
        $this->addSql('ALTER TABLE `option` DROP code');
        $this->addSql('ALTER TABLE option_value DROP FOREIGN KEY FK_249CE55CA7C41D6F');
        $this->addSql('DROP INDEX IDX_249CE55CA7C41D6F ON option_value');
        $this->addSql('ALTER TABLE option_value DROP code, CHANGE option_id option_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE option_value ADD CONSTRAINT FK_249CE55CDDB89BE6 FOREIGN KEY (option_type_id) REFERENCES `option` (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_249CE55CDDB89BE6 ON option_value (option_type_id)');
    }
}
