<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Incremental migration: add fias.kladr_code and alter city_modal.fias_id to VARCHAR(13)
 */
final class Version20250926151000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fias.kladr_code (unique), change city_modal.fias_id to VARCHAR(13)';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        // Add kladr_code to fias with unique index
        if ($sm->tablesExist(['fias'])) {
            $fias = $sm->listTableDetails('fias');
            if (!$fias->hasColumn('kladr_code')) {
                $this->addSql("ALTER TABLE fias ADD kladr_code VARCHAR(13) DEFAULT NULL");
            }
            // Refresh table details after potential column add
            $fias = $sm->listTableDetails('fias');
            $hasUniqueOnKladr = false;
            foreach ($fias->getIndexes() as $idx) {
                if ($idx->isUnique() && $idx->getColumns() === ['kladr_code']) {
                    $hasUniqueOnKladr = true;
                    break;
                }
                if (strtoupper($idx->getName()) === 'UNIQ_FIAS_KLADR_CODE') {
                    $hasUniqueOnKladr = true;
                    break;
                }
            }
            if (!$hasUniqueOnKladr) {
                $this->addSql('CREATE UNIQUE INDEX UNIQ_FIAS_KLADR_CODE ON fias (kladr_code)');
            }
        }

        // Modify city_modal.fias_id type to VARCHAR(13)
        if ($sm->tablesExist(['city_modal'])) {
            $cityModal = $sm->listTableDetails('city_modal');
            if ($cityModal->hasColumn('fias_id')) {
                $col = $cityModal->getColumn('fias_id');
                $type = $col->getType()->getName();
                $length = $col->getLength();
                if ($type !== 'string' || $length !== 13) {
                    $this->addSql('ALTER TABLE city_modal MODIFY fias_id VARCHAR(13) DEFAULT NULL');
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        // Revert city_modal.fias_id back to BIGINT
        if ($sm->tablesExist(['city_modal'])) {
            $cityModal = $sm->listTableDetails('city_modal');
            if ($cityModal->hasColumn('fias_id')) {
                $col = $cityModal->getColumn('fias_id');
                $type = $col->getType()->getName();
                if ($type !== 'bigint' && $type !== 'integer') {
                    $this->addSql('ALTER TABLE city_modal MODIFY fias_id BIGINT DEFAULT NULL');
                }
            }
        }

        // Drop unique index and kladr_code column from fias
        if ($sm->tablesExist(['fias'])) {
            $fias = $sm->listTableDetails('fias');
            foreach ($fias->getIndexes() as $idx) {
                if ($idx->isUnique() && $idx->getColumns() === ['kladr_code']) {
                    $this->addSql('DROP INDEX ' . $idx->getName() . ' ON fias');
                    break;
                }
                if (strtoupper($idx->getName()) === 'UNIQ_FIAS_KLADR_CODE') {
                    $this->addSql('DROP INDEX UNIQ_FIAS_KLADR_CODE ON fias');
                    break;
                }
            }
            // Refresh after index drop
            $fias = $sm->listTableDetails('fias');
            if ($fias->hasColumn('kladr_code')) {
                $this->addSql('ALTER TABLE fias DROP COLUMN kladr_code');
            }
        }
    }
}


