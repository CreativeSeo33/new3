<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250813213105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill users.name from users.email where name is empty';
    }

    public function up(Schema $schema): void
    {
        // Копируем локальную часть email в name, если name пустой
        $this->addSql("UPDATE users SET name = SUBSTRING(email, 1, POSITION('@' IN email) - 1) WHERE (name IS NULL OR name = '') AND email IS NOT NULL AND email <> '' AND POSITION('@' IN email) > 1");
    }

    public function down(Schema $schema): void
    {
        // Откатываем: не трогаем email, просто обнулим name у тех, у кого он совпадает с локальной частью email
        $this->addSql("UPDATE users SET name = '' WHERE POSITION('@' IN email) > 1 AND name = SUBSTRING(email, 1, POSITION('@' IN email) - 1)");
    }
}
