<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250914095900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание таблицы FIAS для Федеральной Информационной Адресной Системы';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fias (fias_id INT AUTO_INCREMENT NOT NULL, parent_id INT NOT NULL, postalcode VARCHAR(6) DEFAULT NULL, offname VARCHAR(120) DEFAULT NULL, shortname VARCHAR(10) DEFAULT NULL, level SMALLINT NOT NULL, INDEX postalcode_idx (postalcode), INDEX offname_idx (offname), INDEX level_idx (level), INDEX parent_id_idx (parent_id), INDEX osl_idx (offname, shortname, level), PRIMARY KEY(fias_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE fias');
    }
}
