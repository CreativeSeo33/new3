<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Table;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812093215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Безопасная миграция SEO: переносим данные в product_seo перед удалением колонок
        // Создаём таблицу, если отсутствует (MySQL синтаксис)
        $this->addSql(<<<SQL
CREATE TABLE IF NOT EXISTS product_seo (
    product_id INT NOT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(255) DEFAULT NULL,
    meta_keywords VARCHAR(255) DEFAULT NULL,
    h1 VARCHAR(255) DEFAULT NULL,
    INDEX IDX_PRODUCT_SEO_PRODUCT (product_id),
    PRIMARY KEY(product_id),
    CONSTRAINT FK_PRODUCT_SEO_PRODUCT FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        // Проверим, существуют ли SEO-колонки в product
        $schemaManager = $this->connection->createSchemaManager();
        $hasMetaTitle = $hasMetaDescription = $hasMetaKeywords = $hasMetaH1 = false;
        if ($schemaManager->tablesExist(['product'])) {
            $table = $schemaManager->introspectTable('product');
            $hasMetaTitle = $table->hasColumn('meta_title');
            $hasMetaDescription = $table->hasColumn('meta_description');
            $hasMetaKeywords = $table->hasColumn('meta_keywords');
            $hasMetaH1 = $table->hasColumn('meta_h1');
        }

        if ($hasMetaTitle || $hasMetaDescription || $hasMetaKeywords || $hasMetaH1) {
            // Копируем существующие SEO-значения в product_seo (INSERT IGNORE для идемпотентности)
            $this->addSql(<<<SQL
INSERT IGNORE INTO product_seo (product_id, meta_title, meta_description, meta_keywords, h1)
SELECT id, meta_title, meta_description, meta_keywords, meta_h1
FROM product
WHERE meta_title IS NOT NULL OR meta_description IS NOT NULL OR meta_keywords IS NOT NULL OR meta_h1 IS NOT NULL
SQL);

            // Удаляем SEO-колонки из product
            $this->addSql('ALTER TABLE product DROP meta_title, DROP meta_description, DROP meta_keywords, DROP meta_h1');
        }
    }

    public function down(Schema $schema): void
    {
        // Возвращаем SEO-колонки в product, если таблица product существует
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['product'])) {
            $this->addSql('ALTER TABLE product ADD meta_title VARCHAR(255) DEFAULT NULL, ADD meta_description VARCHAR(255) DEFAULT NULL, ADD meta_keywords VARCHAR(255) DEFAULT NULL, ADD meta_h1 VARCHAR(255) DEFAULT NULL');

            // Переносим данные обратно из product_seo при наличии
            if ($schemaManager->tablesExist(['product_seo'])) {
                $this->addSql(<<<SQL
UPDATE product p
JOIN product_seo s ON s.product_id = p.id
SET p.meta_title = s.meta_title,
    p.meta_description = s.meta_description,
    p.meta_keywords = s.meta_keywords,
    p.meta_h1 = s.h1
SQL);
                $this->addSql('DROP TABLE IF EXISTS product_seo');
            }
        }
    }
}
