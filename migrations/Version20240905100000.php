<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240905100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cart_idempotency table for idempotent operations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE `cart_idempotency` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `idempotency_key` VARCHAR(255) NOT NULL,
              `cart_id` VARCHAR(26) NOT NULL,
              `endpoint` VARCHAR(255) NOT NULL,
              `request_hash` CHAR(64) NOT NULL,
              `status` VARCHAR(16) NOT NULL,
              `http_status` SMALLINT UNSIGNED NULL,
              `response_data` JSON NULL,
              `instance_id` VARCHAR(64) NULL,
              `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `expires_at` DATETIME(3) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_idem_key` (`idempotency_key`),
              KEY `idx_expires_at` (`expires_at`),
              KEY `idx_cart_id` (`cart_id`),
              KEY `idx_endpoint` (`endpoint`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cart_idempotency');
    }
}
