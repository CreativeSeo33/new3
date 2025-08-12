<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812093144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP meta_title, DROP meta_description, DROP meta_keywords, DROP meta_h1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product ADD meta_title VARCHAR(255) DEFAULT NULL, ADD meta_description VARCHAR(255) DEFAULT NULL, ADD meta_keywords VARCHAR(255) DEFAULT NULL, ADD meta_h1 VARCHAR(255) DEFAULT NULL');
    }
}
