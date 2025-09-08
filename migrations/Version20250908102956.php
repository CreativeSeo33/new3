<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Миграция для устранения дублей позиций корзины и нормализации options_hash
 *
 * Делает поле options_hash NOT NULL с дефолтом '', устраняет дубликаты,
 * гарантируя уникальность по (cart_id, product_id, options_hash)
 */
final class Version20250908102956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix cart item duplicates and normalize options_hash field';
    }

    public function up(Schema $schema): void
    {
        // Шаг 1: Консолидируем дубликаты
        $this->consolidateDuplicates();

        // Шаг 2: Нормализуем NULL → ''
        $this->addSql("UPDATE cart_item SET options_hash = '' WHERE options_hash IS NULL");

        // Шаг 3: Изменяем колонку на NOT NULL с дефолтом
        $this->addSql("ALTER TABLE cart_item MODIFY options_hash VARCHAR(32) NOT NULL DEFAULT ''");

        // Шаг 4: Пересоздаем уникальный индекс (на случай если он поврежден)
        $this->addSql('DROP INDEX uniq_cart_product_options ON cart_item');
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_product_options ON cart_item (cart_id, product_id, options_hash)');
    }

    public function down(Schema $schema): void
    {
        // Откат: делаем поле nullable снова
        $this->addSql("ALTER TABLE cart_item MODIFY options_hash VARCHAR(32) NULL");

        // Откат индекса (хотя уникальность может быть нарушена)
        $this->addSql('DROP INDEX uniq_cart_product_options ON cart_item');
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_product_options ON cart_item (cart_id, product_id, options_hash)');
    }

    private function consolidateDuplicates(): void
    {
        // Находим дубликаты: группируем по (cart_id, product_id, COALESCE(options_hash, ''))
        $duplicatesSql = "
            SELECT
                cart_id,
                product_id,
                COALESCE(options_hash, '') as normalized_hash,
                GROUP_CONCAT(id ORDER BY id) as ids,
                SUM(qty) as total_qty,
                COUNT(*) as count
            FROM cart_item
            GROUP BY cart_id, product_id, COALESCE(options_hash, '')
            HAVING COUNT(*) > 1
        ";

        $duplicates = $this->connection->executeQuery($duplicatesSql)->fetchAllAssociative();

        foreach ($duplicates as $duplicate) {
            $ids = explode(',', $duplicate['ids']);
            $keepId = array_shift($ids); // Оставляем запись с минимальным ID
            $deleteIds = implode(',', $ids);

            // Суммируем количество в основной записи
            $this->addSql("UPDATE cart_item SET qty = {$duplicate['total_qty']} WHERE id = {$keepId}");

            // Удаляем остальные дубликаты
            $this->addSql("DELETE FROM cart_item WHERE id IN ({$deleteIds})");

            // Пересчитываем row_total для основной записи
            $this->addSql("
                UPDATE cart_item ci
                JOIN cart_item ci2 ON ci2.id = {$keepId}
                SET ci.row_total = ci2.effective_unit_price * {$duplicate['total_qty']}
                WHERE ci.id = {$keepId}
            ");
        }
    }
}
