<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250814Shipping extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add shipping fields to cart';
	}

	public function up(Schema $schema): void
	{
		$this->addSql("ALTER TABLE cart ADD shipping_method VARCHAR(64) DEFAULT NULL");
		$this->addSql("ALTER TABLE cart ADD shipping_cost INT NOT NULL DEFAULT 0");
		$this->addSql("ALTER TABLE cart ADD ship_to_city VARCHAR(128) DEFAULT NULL");
		$this->addSql("ALTER TABLE cart ADD shipping_data JSON DEFAULT NULL");
	}

	public function down(Schema $schema): void
	{
		$this->addSql("ALTER TABLE cart DROP shipping_method");
		$this->addSql("ALTER TABLE cart DROP shipping_cost");
		$this->addSql("ALTER TABLE cart DROP ship_to_city");
		$this->addSql("ALTER TABLE cart DROP shipping_data");
	}
}


