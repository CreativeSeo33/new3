<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250910124729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cart_idempotency table for idempotent operations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cart_idempotency (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, idempotency_key VARCHAR(255) NOT NULL, cart_id VARCHAR(26) NOT NULL, endpoint VARCHAR(255) NOT NULL, request_hash VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL, http_status SMALLINT UNSIGNED DEFAULT NULL, response_data JSON DEFAULT NULL, instance_id VARCHAR(64) DEFAULT NULL, created_at DATETIME(3) NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME(3) NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uk_idem_key (idempotency_key), INDEX idx_expires_at (expires_at), INDEX idx_cart_id (cart_id), INDEX idx_endpoint (endpoint), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cart_idempotency');
    }
}
