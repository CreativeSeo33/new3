<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add unique index for product.slug and secondary indexes for status/manufacturer/date_added/sort_order.
 * Also normalizes existing slug values to avoid unique violations.
 */
final class Version20250812121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique index on product.slug; add indexes on status, manufacturer, date_added, sort_order; normalize existing slugs.';
    }

    public function up(Schema $schema): void
    {
        // 1) Ensure non-empty slugs
        $this->addSql("UPDATE product SET slug = CONCAT('product-', id) WHERE slug IS NULL OR slug = ''");

        // 2) De-duplicate existing slugs by appending -{id} for duplicates beyond the first
        $duplicateSlugs = $this->connection->fetchFirstColumn('SELECT slug FROM product GROUP BY slug HAVING COUNT(*) > 1');
        if (!empty($duplicateSlugs)) {
            // Use parameterized IN query to fetch all rows for those slugs
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, slug FROM product WHERE slug IN (?) ORDER BY slug ASC, id ASC',
                [$duplicateSlugs],
                [ArrayParameterType::STRING]
            );

            $currentSlug = null;
            $seenCount = 0;
            foreach ($rows as $row) {
                if ($row['slug'] !== $currentSlug) {
                    $currentSlug = $row['slug'];
                    $seenCount = 0;
                }
                $seenCount++;
                if ($seenCount > 1) {
                    $newSlug = $row['slug'] . '-' . $row['id'];
                    $this->addSql('UPDATE product SET slug = ? WHERE id = ?', [$newSlug, (int) $row['id']]);
                }
            }
        }

        // 3) Add unique index for slug
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04AD989D9B62 ON product (slug)');

        // 4) Secondary indexes
        $this->addSql('CREATE INDEX product_status_idx ON product (status)');
        $this->addSql('CREATE INDEX product_manufacturer_idx ON product (manufacturer)');
        $this->addSql('CREATE INDEX product_date_added_idx ON product (date_added)');
        $this->addSql('CREATE INDEX product_sort_order_idx ON product (sort_order)');
    }

    public function down(Schema $schema): void
    {
        // Drop added indexes (data changes to slug are not reverted)
        $this->addSql('DROP INDEX UNIQ_D34A04AD989D9B62 ON product');
        $this->addSql('DROP INDEX product_status_idx ON product');
        $this->addSql('DROP INDEX product_manufacturer_idx ON product');
        $this->addSql('DROP INDEX product_date_added_idx ON product');
        $this->addSql('DROP INDEX product_sort_order_idx ON product');
    }
}


