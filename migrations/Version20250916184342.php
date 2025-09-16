<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250916184342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_delivery DROP FOREIGN KEY FK_ORDER_DELIVERY_CITY');
        $this->addSql('DROP INDEX IDX_ORDER_DELIVERY_CITY_ID ON order_delivery');
        $this->addSql('ALTER TABLE product ADD type VARCHAR(32) DEFAULT \'simple\' NOT NULL');
        $this->addSql('ALTER TABLE product_option_value_assignment ADD set_price TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE pvz_points DROP FOREIGN KEY FK_PVZ_POINTS_CITY');
        $this->addSql('DROP INDEX IDX_PVZ_POINTS_CITY_ID ON pvz_points');
        $this->addSql('ALTER TABLE pvz_points CHANGE price price INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pvz_price DROP FOREIGN KEY FK_PVZ_PRICE_CITY');
        $this->addSql('DROP INDEX IDX_PVZ_PRICE_CITY_ID ON pvz_price');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX IDX_ORDER_DELIVERY_CITY_ID ON order_delivery (city_id)');
        $this->addSql('ALTER TABLE product DROP type');
        $this->addSql('ALTER TABLE product_option_value_assignment DROP set_price');
        $this->addSql('ALTER TABLE pvz_points CHANGE price price DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_PVZ_POINTS_CITY_ID ON pvz_points (city_id)');
        $this->addSql('CREATE INDEX IDX_PVZ_PRICE_CITY_ID ON pvz_price (city_id)');
    }
}
