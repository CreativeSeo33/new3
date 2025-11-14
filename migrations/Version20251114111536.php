<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251114111536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE related_products ADD related_product_id INT NOT NULL');
        $this->addSql('ALTER TABLE related_products ADD CONSTRAINT FK_153914F7CF496EEA FOREIGN KEY (related_product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_related_product_related ON related_products (related_product_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE related_products DROP FOREIGN KEY FK_153914F7CF496EEA');
        $this->addSql('DROP INDEX idx_related_product_related ON related_products');
        $this->addSql('ALTER TABLE related_products DROP related_product_id');
    }
}
