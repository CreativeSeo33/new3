<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250908093655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add priced_at to cart_item
        $this->addSql("ALTER TABLE cart_item ADD priced_at DATETIME NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('UPDATE cart_item SET priced_at = NOW() WHERE priced_at IS NULL');
        $this->addSql("ALTER TABLE cart_item MODIFY priced_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
