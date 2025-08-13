<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250813190500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable name column to users table';
    }

    public function up(Schema $schema): void
    {
        // Add name column for App\\Entity\\User
        $this->addSql('ALTER TABLE users ADD name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN name');
    }
}


