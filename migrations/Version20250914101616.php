<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250914101616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ensure FIAS table exists
        $this->addSql("CREATE TABLE IF NOT EXISTS fias (
            fias_id INT AUTO_INCREMENT NOT NULL,
            parent_id INT NOT NULL,
            postalcode VARCHAR(6) DEFAULT NULL,
            offname VARCHAR(120) DEFAULT NULL,
            shortname VARCHAR(10) DEFAULT NULL,
            level SMALLINT NOT NULL,
            INDEX postalcode_idx (postalcode),
            INDEX offname_idx (offname),
            INDEX level_idx (level),
            INDEX parent_id_idx (parent_id),
            INDEX osl_idx (offname, shortname, level),
            PRIMARY KEY(fias_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        // Align FIAS level type and index names (best-effort)
        $this->addSql('ALTER TABLE fias CHANGE level level SMALLINT NOT NULL');
        $this->renameIndexIfExists('fias', 'postalcode', 'postalcode_idx');
        $this->renameIndexIfExists('fias', 'offname', 'offname_idx');
        $this->renameIndexIfExists('fias', 'level', 'level_idx');
        $this->renameIndexIfExists('fias', 'parent_id', 'parent_id_idx');
        $this->renameIndexIfExists('fias', 'osl', 'osl_idx');

        // order_delivery.city_id
        if (!$this->columnExists('order_delivery', 'city_id')) {
            $this->addSql('ALTER TABLE order_delivery ADD city_id INT DEFAULT NULL');
        }
        if (!$this->indexExists('order_delivery', 'IDX_D6790EA18BAC62AF')) {
            $this->addSql('CREATE INDEX IDX_D6790EA18BAC62AF ON order_delivery (city_id)');
        }
        if (!$this->fkExists('order_delivery', 'FK_D6790EA18BAC62AF')) {
            $this->addSql('ALTER TABLE order_delivery ADD CONSTRAINT FK_D6790EA18BAC62AF FOREIGN KEY (city_id) REFERENCES fias (fias_id) ON DELETE SET NULL');
        }

        // pvz_points.city_id
        if (!$this->columnExists('pvz_points', 'city_id')) {
            $this->addSql('ALTER TABLE pvz_points ADD city_id INT DEFAULT NULL');
        }
        if (!$this->indexExists('pvz_points', 'IDX_E80F6C3D8BAC62AF')) {
            $this->addSql('CREATE INDEX IDX_E80F6C3D8BAC62AF ON pvz_points (city_id)');
        }
        if (!$this->fkExists('pvz_points', 'FK_E80F6C3D8BAC62AF')) {
            $this->addSql('ALTER TABLE pvz_points ADD CONSTRAINT FK_E80F6C3D8BAC62AF FOREIGN KEY (city_id) REFERENCES fias (fias_id) ON DELETE SET NULL');
        }

        // pvz_price.city_id
        if (!$this->columnExists('pvz_price', 'city_id')) {
            $this->addSql('ALTER TABLE pvz_price ADD city_id INT DEFAULT NULL');
        }
        if (!$this->indexExists('pvz_price', 'IDX_C5BFAFEF8BAC62AF')) {
            $this->addSql('CREATE INDEX IDX_C5BFAFEF8BAC62AF ON pvz_price (city_id)');
        }
        if (!$this->fkExists('pvz_price', 'FK_C5BFAFEF8BAC62AF')) {
            $this->addSql('ALTER TABLE pvz_price ADD CONSTRAINT FK_C5BFAFEF8BAC62AF FOREIGN KEY (city_id) REFERENCES fias (fias_id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fias CHANGE level level TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE fias RENAME INDEX level_idx TO level');
        $this->addSql('ALTER TABLE fias RENAME INDEX offname_idx TO offname');
        $this->addSql('ALTER TABLE fias RENAME INDEX osl_idx TO osl');
        $this->addSql('ALTER TABLE fias RENAME INDEX parent_id_idx TO parent_id');
        $this->addSql('ALTER TABLE fias RENAME INDEX postalcode_idx TO postalcode');
        $this->addSql('ALTER TABLE order_delivery DROP FOREIGN KEY FK_D6790EA18BAC62AF');
        $this->addSql('DROP INDEX IDX_D6790EA18BAC62AF ON order_delivery');
        $this->addSql('ALTER TABLE order_delivery DROP city_id');
        $this->addSql('ALTER TABLE pvz_points DROP FOREIGN KEY FK_E80F6C3D8BAC62AF');
        $this->addSql('DROP INDEX IDX_E80F6C3D8BAC62AF ON pvz_points');
        $this->addSql('ALTER TABLE pvz_points DROP city_id');
        $this->addSql('ALTER TABLE pvz_price DROP FOREIGN KEY FK_C5BFAFEF8BAC62AF');
        $this->addSql('DROP INDEX IDX_C5BFAFEF8BAC62AF ON pvz_price');
        $this->addSql('ALTER TABLE pvz_price DROP city_id');
    }

    private function tableExists(string $table): bool
    {
        $sql = "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t";
        return (int)$this->connection->fetchOne($sql, ['t' => $table]) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c";
        return (int)$this->connection->fetchOne($sql, ['t' => $table, 'c' => $column]) > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $sql = "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i";
        return (int)$this->connection->fetchOne($sql, ['t' => $table, 'i' => $index]) > 0;
    }

    private function fkExists(string $table, string $constraintName): bool
    {
        $sql = "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :n AND TABLE_NAME = :t";
        return (int)$this->connection->fetchOne($sql, ['n' => $constraintName, 't' => $table]) > 0;
    }

    private function renameIndexIfExists(string $table, string $oldName, string $newName): void
    {
        if ($this->indexExists($table, $oldName) && !$this->indexExists($table, $newName)) {
            $this->addSql(sprintf('ALTER TABLE %s RENAME INDEX %s TO %s', $table, $oldName, $newName));
        }
    }
}
