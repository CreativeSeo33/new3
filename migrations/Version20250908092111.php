<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Миграция для перевода float полей цен в int рубли
 *
 * Переводит все денежные поля из float в int для обеспечения
 * целочисленной арифметики рублей без копеек.
 */
final class Version20250908092111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert float price fields to int for RUB-only arithmetic';
    }

    public function up(Schema $schema): void
    {
        // Переводим поле price в таблице pvz_points из float в int
        $this->addSql('ALTER TABLE pvz_points MODIFY price INT DEFAULT NULL');

        // Проверяем, что все значения целые (без дробной части)
        $this->addSql("
            UPDATE pvz_points
            SET price = ROUND(price)
            WHERE price IS NOT NULL AND price != ROUND(price)
        ");
    }

    public function down(Schema $schema): void
    {
        // Обратная миграция: переводим обратно в float
        $this->addSql('ALTER TABLE pvz_points MODIFY price FLOAT(10,0) DEFAULT NULL');
    }
}
