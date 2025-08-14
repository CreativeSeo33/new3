<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250814090000 extends AbstractMigration
{
	public function getDescription(): string { return 'Create cart and cart_item tables'; }

	public function up(Schema $schema): void
	{
		$this->addSql('CREATE TABLE cart (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, token CHAR(36) DEFAULT NULL, currency CHAR(3) NOT NULL, subtotal INT NOT NULL DEFAULT 0, discount_total INT NOT NULL DEFAULT 0, total INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, version INT NOT NULL DEFAULT 1, UNIQUE INDEX UNIQ_05EFA9AAF47645AE (token), INDEX IDX_05EFA9AA76ED395 (user_id), INDEX IDX_05EFA9AA6A2C3FC (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
		$this->addSql('CREATE TABLE cart_item (id INT AUTO_INCREMENT NOT NULL, cart_id INT NOT NULL, product_id INT NOT NULL, product_name VARCHAR(255) NOT NULL, unit_price INT NOT NULL, qty INT NOT NULL, row_total INT NOT NULL, version INT NOT NULL DEFAULT 1, INDEX IDX_F0FE25271AD5CDBF (cart_id), INDEX IDX_F0FE25274584665A (product_id), UNIQUE INDEX uniq_cart_product (cart_id, product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
		$this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE');
		$this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25274584665A FOREIGN KEY (product_id) REFERENCES product (id)');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25271AD5CDBF');
		$this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25274584665A');
		$this->addSql('DROP TABLE cart_item');
		$this->addSql('DROP TABLE cart');
	}
}


