<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250819104235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE option_value DROP FOREIGN KEY FK_249CE55CA7C41D6F');
        $this->addSql('ALTER TABLE option_value CHANGE option_id option_id INT NOT NULL');
        $this->addSql('ALTER TABLE option_value ADD CONSTRAINT FK_249CE55CA7C41D6F FOREIGN KEY (option_id) REFERENCES `option` (id)');
        $this->addSql('ALTER TABLE product_option_value_assignment DROP FOREIGN KEY FK_E85761E8A7C41D6F');
        $this->addSql('ALTER TABLE product_option_value_assignment DROP FOREIGN KEY FK_E85761E8F920BBA2');
        $this->addSql('ALTER TABLE product_option_value_assignment ADD CONSTRAINT FK_E85761E8A7C41D6F FOREIGN KEY (option_id) REFERENCES `option` (id)');
        $this->addSql('ALTER TABLE product_option_value_assignment ADD CONSTRAINT FK_E85761E8F920BBA2 FOREIGN KEY (value_id) REFERENCES option_value (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE option_value DROP FOREIGN KEY FK_249CE55CA7C41D6F');
        $this->addSql('ALTER TABLE option_value CHANGE option_id option_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE option_value ADD CONSTRAINT FK_249CE55CA7C41D6F FOREIGN KEY (option_id) REFERENCES `option` (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_option_value_assignment DROP FOREIGN KEY FK_E85761E8A7C41D6F');
        $this->addSql('ALTER TABLE product_option_value_assignment DROP FOREIGN KEY FK_E85761E8F920BBA2');
        $this->addSql('ALTER TABLE product_option_value_assignment ADD CONSTRAINT FK_E85761E8A7C41D6F FOREIGN KEY (option_id) REFERENCES `option` (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_option_value_assignment ADD CONSTRAINT FK_E85761E8F920BBA2 FOREIGN KEY (value_id) REFERENCES option_value (id) ON UPDATE NO ACTION ON DELETE CASCADE');
    }
}
