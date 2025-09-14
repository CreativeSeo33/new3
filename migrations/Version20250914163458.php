<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250914163458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Удаляем FK на product_id -> order_products, если он существует (имя может отличаться между средами)
        $schemaManager = $this->connection->createSchemaManager();
        try {
            foreach ($schemaManager->listTableForeignKeys('order_product_options') as $fk) {
                if (in_array('product_id', $fk->getLocalColumns(), true) && $fk->getForeignTableName() === 'order_products') {
                    $this->addSql('ALTER TABLE order_product_options DROP FOREIGN KEY `'.$fk->getName().'`');
                }
            }
        } catch (\Throwable $e) {
            // no-op: продолжаем даже если получить список FK не удалось
        }

        // Добавляем новый столбец для связи с order_products (делаем временно NULLable для безопасного деплоя)
        $columns = [];
        try {
            foreach ($schemaManager->listTableColumns('order_product_options') as $col) {
                $columns[strtolower($col->getName())] = true;
            }
        } catch (\Throwable $e) {}
        if (!isset($columns['order_product_id'])) {
            $this->addSql('ALTER TABLE order_product_options ADD order_product_id INT DEFAULT NULL');
        }

        // Индекс (создаём, только если нет)
        $indexes = [];
        try {
            foreach ($schemaManager->listTableIndexes('order_product_options') as $idx) {
                $indexes[$idx->getName()] = $idx;
            }
        } catch (\Throwable $e) {}
        if (!isset($indexes['IDX_CAE5226BF65E9B0F'])) {
            $this->addSql('CREATE INDEX IDX_CAE5226BF65E9B0F ON order_product_options (order_product_id)');
        }

        // Внешний ключ (создаём, только если нет)
        $hasNewFk = false;
        try {
            foreach ($schemaManager->listTableForeignKeys('order_product_options') as $fk) {
                if (in_array('order_product_id', $fk->getLocalColumns(), true) && $fk->getForeignTableName() === 'order_products') {
                    $hasNewFk = true;
                    break;
                }
            }
        } catch (\Throwable $e) {}
        if (!$hasNewFk) {
            $this->addSql('ALTER TABLE order_product_options ADD CONSTRAINT FK_CAE5226BF65E9B0F FOREIGN KEY (order_product_id) REFERENCES order_products (id) ON DELETE CASCADE');
        }

        // Бэкофилл: копируем существующие связи из старой колонки product_id
        $this->addSql('UPDATE order_product_options SET order_product_id = product_id WHERE order_product_id IS NULL');

        // Делаем NOT NULL после бэкофилла (если столбец существует)
        $this->addSql('ALTER TABLE order_product_options MODIFY order_product_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Откатываем добавление нового FK и столбца
        $this->addSql('ALTER TABLE order_product_options DROP FOREIGN KEY FK_CAE5226BF65E9B0F');
        $this->addSql('DROP INDEX IDX_CAE5226BF65E9B0F ON order_product_options');
        $this->addSql('ALTER TABLE order_product_options DROP order_product_id');
        // Пытаемся вернуть старый FK, если он предполагался
        $this->addSql('ALTER TABLE order_product_options ADD CONSTRAINT FK_CAE5226B4584665A FOREIGN KEY (product_id) REFERENCES order_products (id)');
    }
}
