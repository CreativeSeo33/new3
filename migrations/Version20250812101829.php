<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812101829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add effective_price column and index, backfill values, drop legacy manufacturer column and its index if exist.';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['product'])) {
            $product = $sm->introspectTable('product');

            if (!$product->hasColumn('effective_price')) {
                $this->addSql('ALTER TABLE product ADD effective_price INT DEFAULT NULL');
                $this->addSql('CREATE INDEX product_effective_price_idx ON product (effective_price)');
                $this->addSql('UPDATE product SET effective_price = COALESCE(sale_price, price)');
            }

            if ($product->hasColumn('manufacturer')) {
                // Drop index if it exists
                try {
                    $this->addSql('DROP INDEX product_manufacturer_idx ON product');
                } catch (\Throwable $e) {
                    // ignore
                }
                $this->addSql('ALTER TABLE product DROP manufacturer');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['product'])) {
            $product = $sm->introspectTable('product');
            if ($product->hasColumn('effective_price')) {
                $this->addSql('DROP INDEX product_effective_price_idx ON product');
                $this->addSql('ALTER TABLE product DROP effective_price');
            }
            if (!$product->hasColumn('manufacturer')) {
                $this->addSql('ALTER TABLE product ADD manufacturer INT DEFAULT NULL');
                $this->addSql('CREATE INDEX product_manufacturer_idx ON product (manufacturer)');
            }
        }
    }
}
