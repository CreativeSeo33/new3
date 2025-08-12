<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812094445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create manufacturer table, add product.manufacturer_id FK, migrate data from old manufacturer column, add currency column if missing.';
    }

    public function up(Schema $schema): void
    {
        // Detect product table collation to avoid mix-collation issues
        $row = $this->connection->fetchAssociative("SHOW TABLE STATUS WHERE Name = 'product'");
        $productCollation = is_array($row) && isset($row['Collation']) && $row['Collation']
            ? $row['Collation']
            : 'utf8mb4_general_ci';
        $charset = explode('_', $productCollation)[0] ?? 'utf8mb4';

        // Create manufacturer table if not exists
        $this->addSql(<<<SQL
CREATE TABLE IF NOT EXISTS manufacturer (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(255) NOT NULL,
    UNIQUE INDEX UNIQ_MANUFACTURER_NAME (name),
    INDEX manufacturer_name_idx (name),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET {$charset} COLLATE `{$productCollation}` ENGINE = InnoDB
SQL);

        // Ensure table collation matches product's
        $this->addSql("ALTER TABLE manufacturer CONVERT TO CHARACTER SET {$charset} COLLATE {$productCollation}");

        // Add manufacturer_id column to product if not exists
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['product'])) {
            $product = $sm->introspectTable('product');
            if (!$product->hasColumn('manufacturer_id')) {
                $this->addSql('ALTER TABLE product ADD manufacturer_id INT DEFAULT NULL');
                $this->addSql('CREATE INDEX IDX_PRODUCT_MANUFACTURER_ID ON product (manufacturer_id)');
                $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_PRODUCT_MANUFACTURER_ID FOREIGN KEY (manufacturer_id) REFERENCES manufacturer (id) ON DELETE SET NULL');
            }

            // If currency column missing (from embeddable), add it
            if (!$product->hasColumn('currency')) {
                $this->addSql("ALTER TABLE product ADD currency VARCHAR(3) DEFAULT 'RUB' NOT NULL");
            }
        }

        // Migrate numeric manufacturer -> manufacturer table and set FK
        // 1) Insert missing manufacturers by name = CAST(old_id AS CHAR)
        $this->addSql(<<<SQL
INSERT INTO manufacturer (name)
SELECT DISTINCT CAST(manufacturer AS CHAR(255)) COLLATE {$productCollation}
FROM product
WHERE manufacturer IS NOT NULL
  AND CAST(manufacturer AS CHAR(255)) COLLATE {$productCollation} NOT IN (
      SELECT name FROM manufacturer
  )
SQL);

        // 2) Set product.manufacturer_id by numeric join to avoid collation issues
        $this->addSql(<<<SQL
UPDATE product p
JOIN manufacturer m ON CAST(m.name AS UNSIGNED) = p.manufacturer
SET p.manufacturer_id = m.id
WHERE p.manufacturer IS NOT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        // Drop FK and column manufacturer_id if exists
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['product'])) {
            $product = $sm->introspectTable('product');
            if ($product->hasColumn('manufacturer_id')) {
                $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_PRODUCT_MANUFACTURER_ID');
                $this->addSql('DROP INDEX IDX_PRODUCT_MANUFACTURER_ID ON product');
                $this->addSql('ALTER TABLE product DROP manufacturer_id');
            }
            if ($product->hasColumn('currency')) {
                $this->addSql('ALTER TABLE product DROP currency');
            }
        }
        // Drop manufacturer table (data loss acceptable for down)
        $this->addSql('DROP TABLE IF EXISTS manufacturer');
    }
}
