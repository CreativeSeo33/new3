<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250819101756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `option` CHANGE code code VARCHAR(100) DEFAULT NULL');
        // normalize empty strings to NULL to satisfy UNIQUE index
        $this->addSql("UPDATE `option` SET code = NULL WHERE code = ''");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8600B077153098 ON `option` (code)');
        $this->addSql('ALTER TABLE option_value DROP FOREIGN KEY FK_249CE55CDDB89BE6');
        $this->addSql('DROP INDEX IDX_249CE55CDDB89BE6 ON option_value');
        $this->addSql('ALTER TABLE option_value ADD code VARCHAR(100) DEFAULT NULL, CHANGE option_type_id option_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE option_value ADD CONSTRAINT FK_249CE55CA7C41D6F FOREIGN KEY (option_id) REFERENCES `option` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_249CE55CA7C41D6F ON option_value (option_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_value_code_per_option ON option_value (option_id, code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_5A8600B077153098 ON `option`');
        $this->addSql('ALTER TABLE `option` CHANGE code code VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE option_value DROP FOREIGN KEY FK_249CE55CA7C41D6F');
        $this->addSql('DROP INDEX IDX_249CE55CA7C41D6F ON option_value');
        $this->addSql('ALTER TABLE option_value DROP code, CHANGE option_id option_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE option_value ADD CONSTRAINT FK_249CE55CDDB89BE6 FOREIGN KEY (option_type_id) REFERENCES `option` (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_249CE55CDDB89BE6 ON option_value (option_type_id)');
    }
}
