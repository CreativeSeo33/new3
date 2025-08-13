<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250813182200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `option` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, sort_order INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE option_value (id INT AUTO_INCREMENT NOT NULL, option_type_id INT DEFAULT NULL, value VARCHAR(255) NOT NULL, sort_order INT NOT NULL, INDEX IDX_249CE55CDDB89BE6 (option_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE option_value ADD CONSTRAINT FK_249CE55CDDB89BE6 FOREIGN KEY (option_type_id) REFERENCES `option` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE option_value DROP FOREIGN KEY FK_249CE55CDDB89BE6');
        $this->addSql('DROP TABLE `option`');
        $this->addSql('DROP TABLE option_value');
    }
}
