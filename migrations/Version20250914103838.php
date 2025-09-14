<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250914103838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_delivery ADD pricing_source VARCHAR(32) DEFAULT NULL, ADD pricing_trace JSON DEFAULT NULL, CHANGE is_free is_free TINYINT(1) DEFAULT 0 NOT NULL, CHANGE is_custom_calculate is_custom_calculate TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_delivery DROP pricing_source, DROP pricing_trace, CHANGE is_free is_free TINYINT(1) DEFAULT NULL, CHANGE is_custom_calculate is_custom_calculate TINYINT(1) DEFAULT NULL');
    }
}
