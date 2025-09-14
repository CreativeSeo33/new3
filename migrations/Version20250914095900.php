<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250914095900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание таблицы FIAS для Федеральной Информационной Адресной Системы';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fias (fias_id INT AUTO_INCREMENT NOT NULL, parent_id INT NOT NULL, postalcode VARCHAR(6) DEFAULT NULL, offname VARCHAR(120) DEFAULT NULL, shortname VARCHAR(10) DEFAULT NULL, level SMALLINT NOT NULL, INDEX postalcode_idx (postalcode), INDEX offname_idx (offname), INDEX level_idx (level), INDEX parent_id_idx (parent_id), INDEX osl_idx (offname, shortname, level), PRIMARY KEY(fias_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        // Дополнительные колонки city_id и FK в существующих таблицах
        $this->addSql("ALTER TABLE pvz_points ADD city_id INT DEFAULT NULL");
        $this->addSql("ALTER TABLE pvz_points ADD CONSTRAINT FK_PVZ_POINTS_CITY FOREIGN KEY (city_id) REFERENCES fias (fias_id) ON DELETE SET NULL");
        $this->addSql("CREATE INDEX IDX_PVZ_POINTS_CITY_ID ON pvz_points (city_id)");

        $this->addSql("ALTER TABLE pvz_price ADD city_id INT DEFAULT NULL");
        $this->addSql("ALTER TABLE pvz_price ADD CONSTRAINT FK_PVZ_PRICE_CITY FOREIGN KEY (city_id) REFERENCES fias (fias_id) ON DELETE SET NULL");
        $this->addSql("CREATE INDEX IDX_PVZ_PRICE_CITY_ID ON pvz_price (city_id)");

        $this->addSql("ALTER TABLE order_delivery ADD city_id INT DEFAULT NULL");
        $this->addSql("ALTER TABLE order_delivery ADD CONSTRAINT FK_ORDER_DELIVERY_CITY FOREIGN KEY (city_id) REFERENCES fias (fias_id) ON DELETE SET NULL");
        $this->addSql("CREATE INDEX IDX_ORDER_DELIVERY_CITY_ID ON order_delivery (city_id)");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE fias');
    }
}
