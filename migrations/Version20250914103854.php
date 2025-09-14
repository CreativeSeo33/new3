<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250914103854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Добавляем отсутствующие колонки и приводим булевы флаги к NOT NULL DEFAULT 0
        if (!$this->columnExists('order_delivery', 'pricing_source')) {
            $this->addSql("ALTER TABLE order_delivery ADD pricing_source VARCHAR(32) DEFAULT NULL");
        }
        if (!$this->columnExists('order_delivery', 'pricing_trace')) {
            $this->addSql("ALTER TABLE order_delivery ADD pricing_trace JSON DEFAULT NULL");
        }

        // Приводим NULL -> 0 перед изменением NOT NULL
        $this->addSql("UPDATE order_delivery SET is_free = 0 WHERE is_free IS NULL");
        $this->addSql("UPDATE order_delivery SET is_custom_calculate = 0 WHERE is_custom_calculate IS NULL");

        // Устанавливаем NOT NULL + DEFAULT 0 (идемпоентно допустимо вызывать повторно)
        $this->addSql("ALTER TABLE order_delivery CHANGE is_free is_free TINYINT(1) DEFAULT 0 NOT NULL");
        $this->addSql("ALTER TABLE order_delivery CHANGE is_custom_calculate is_custom_calculate TINYINT(1) DEFAULT 0 NOT NULL");
    }

    public function down(Schema $schema): void
    {
        // Возвращаем флаги к NULLABLE и удаляем колонки, если существуют
        $this->addSql('ALTER TABLE order_delivery CHANGE is_free is_free TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE order_delivery CHANGE is_custom_calculate is_custom_calculate TINYINT(1) DEFAULT NULL');
        if ($this->columnExists('order_delivery', 'pricing_source')) {
            $this->addSql('ALTER TABLE order_delivery DROP pricing_source');
        }
        if ($this->columnExists('order_delivery', 'pricing_trace')) {
            $this->addSql('ALTER TABLE order_delivery DROP pricing_trace');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c";
        return (int)$this->connection->fetchOne($sql, ['t' => $table, 'c' => $column]) > 0;
    }
}
