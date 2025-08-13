<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250813224030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop email column from users and its unique index';
    }

    public function up(Schema $schema): void
    {
        // Drop unique index on email if present, then drop column
        $this->addSql('ALTER TABLE users DROP INDEX UNIQ_1483A5E9E7927C74');
        $this->addSql('ALTER TABLE users DROP COLUMN email');
    }

    public function down(Schema $schema): void
    {
        // Recreate column and unique index
        $this->addSql("ALTER TABLE users ADD email VARCHAR(255) NOT NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
    }
}


