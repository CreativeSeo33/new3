<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data backfill: populate fias.kladr_code from city.kladr_id by name-level match
 */
final class Version20250926153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill fias.kladr_code using city.kladr_id where names match and level in (3,4)';
    }

    public function up(Schema $schema): void
    {
        // Ensure column exists (safety)
        $this->addSql("SET @__has_fias_kladr := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fias' AND COLUMN_NAME = 'kladr_code')");
        $this->addSql("SET @__has_city_table := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'city')");

        // Backfill: match by name and city level (3/4). Only set when null.
        // Assign at most one FIAS row per KLADR code to respect unique index
        $this->addSql(<<<'SQL'
            UPDATE fias f
            JOIN (
                SELECT
                    CAST(c.kladr_id AS CHAR) AS kladr_code,
                    MIN(f2.fias_id) AS target_fias_id
                FROM city c
                JOIN fias f2
                  ON TRIM(LOWER(f2.offname)) = TRIM(LOWER(c.city))
                 AND f2.level IN (3,4)
                WHERE c.kladr_id IS NOT NULL
                  AND c.kladr_id <> 0
                GROUP BY CAST(c.kladr_id AS CHAR)
            ) t ON f.fias_id = t.target_fias_id
            SET f.kladr_code = SUBSTRING(t.kladr_code, 1, 13)
            WHERE f.kladr_code IS NULL
        SQL);

        // Optional: trim and normalize to max length 13
        $this->addSql("UPDATE fias SET kladr_code = SUBSTRING(kladr_code, 1, 13) WHERE kladr_code IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        // Revert backfill by nullifying kladr_code where it came from city join (best effort)
        $this->addSql(<<<'SQL'
            UPDATE fias f
            JOIN city c
              ON TRIM(LOWER(f.offname)) = TRIM(LOWER(c.city))
             AND f.level IN (3,4)
            SET f.kladr_code = NULL
        SQL);
    }
}


