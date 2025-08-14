<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250814125758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, customer_id INT DEFAULT NULL, delivery_id INT DEFAULT NULL, order_id INT NOT NULL, date_added DATETIME NOT NULL, comment VARCHAR(255) DEFAULT NULL, status INT DEFAULT NULL, total INT DEFAULT NULL, UNIQUE INDEX customer_id (customer_id), UNIQUE INDEX delivery_id (delivery_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `order_customer` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, ip VARCHAR(255) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, phone_normal BIGINT DEFAULT NULL, comment VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE order_delivery (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, cost INT DEFAULT NULL, pvz VARCHAR(255) DEFAULT NULL, is_free TINYINT(1) DEFAULT NULL, is_custom_calculate TINYINT(1) DEFAULT NULL, pvz_code VARCHAR(255) DEFAULT NULL, delivery_date DATE DEFAULT NULL, delivery_time TIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE order_product_options (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, option_name VARCHAR(255) DEFAULT NULL, value JSON DEFAULT NULL, price INT DEFAULT NULL, INDEX product_id (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE order_products (id INT AUTO_INCREMENT NOT NULL, orders_id INT DEFAULT NULL, product_id INT NOT NULL, product_name VARCHAR(255) NOT NULL, price INT DEFAULT NULL, quantity INT NOT NULL, sale_price INT DEFAULT NULL, INDEX orders_id (orders_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE order_status (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(20) NOT NULL, sort INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES `order_customer` (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F529939812136921 FOREIGN KEY (delivery_id) REFERENCES order_delivery (id)');
        $this->addSql('ALTER TABLE order_product_options ADD CONSTRAINT FK_CAE5226B4584665A FOREIGN KEY (product_id) REFERENCES order_products (id)');
        $this->addSql('ALTER TABLE order_products ADD CONSTRAINT FK_5242B8EBCFFE9AD6 FOREIGN KEY (orders_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE cart CHANGE token token VARCHAR(36) DEFAULT NULL, CHANGE currency currency VARCHAR(3) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE expires_at expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_BA388B75F37A13B ON cart (token)');
        $this->addSql('ALTER TABLE cart RENAME INDEX uniq_05efa9aaf47645ae TO UNIQ_BA388B75F37A13B');
        $this->addSql('ALTER TABLE cart RENAME INDEX idx_05efa9aa76ed395 TO IDX_BA388B7A76ED395');
        $this->addSql('ALTER TABLE cart RENAME INDEX idx_05efa9aa6a2c3fc TO IDX_BA388B7F9D83E2');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F529939812136921');
        $this->addSql('ALTER TABLE order_product_options DROP FOREIGN KEY FK_CAE5226B4584665A');
        $this->addSql('ALTER TABLE order_products DROP FOREIGN KEY FK_5242B8EBCFFE9AD6');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE `order_customer`');
        $this->addSql('DROP TABLE order_delivery');
        $this->addSql('DROP TABLE order_product_options');
        $this->addSql('DROP TABLE order_products');
        $this->addSql('DROP TABLE order_status');
        $this->addSql('DROP INDEX IDX_BA388B75F37A13B ON cart');
        $this->addSql('ALTER TABLE cart CHANGE token token CHAR(36) DEFAULT NULL, CHANGE currency currency CHAR(3) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE expires_at expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE cart RENAME INDEX uniq_ba388b75f37a13b TO UNIQ_05EFA9AAF47645AE');
        $this->addSql('ALTER TABLE cart RENAME INDEX idx_ba388b7a76ed395 TO IDX_05EFA9AA76ED395');
        $this->addSql('ALTER TABLE cart RENAME INDEX idx_ba388b7f9d83e2 TO IDX_05EFA9AA6A2C3FC');
    }
}
