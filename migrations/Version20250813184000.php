<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250813184000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add option and option_value tables';
    }

    public function up(Schema $schema): void
    {
        // Create `option` table if not exists
        if (!$schema->hasTable('option')) {
            $this->addSql('CREATE TABLE `option` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, sort_order INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // Create `option_value` table if not exists
        if (!$schema->hasTable('option_value')) {
            $this->addSql('CREATE TABLE option_value (id INT AUTO_INCREMENT NOT NULL, option_type_id INT DEFAULT NULL, `value` VARCHAR(255) NOT NULL, sort_order INT NOT NULL, INDEX option_type_id (option_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE option_value ADD CONSTRAINT FK_OPTION_VALUE_OPTION_TYPE FOREIGN KEY (option_type_id) REFERENCES `option` (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE option_value DROP FOREIGN KEY FK_OPTION_VALUE_OPTION_TYPE');
        $this->addSql('DROP TABLE option_value');
        $this->addSql('DROP TABLE `option`');
    }
}


