<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250914163436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_product_options DROP FOREIGN KEY FK_CAE5226B4584665A');
        $this->addSql('ALTER TABLE order_product_options ADD order_product_id INT NOT NULL');
        $this->addSql('ALTER TABLE order_product_options ADD CONSTRAINT FK_CAE5226BF65E9B0F FOREIGN KEY (order_product_id) REFERENCES order_products (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_CAE5226BF65E9B0F ON order_product_options (order_product_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_product_options DROP FOREIGN KEY FK_CAE5226BF65E9B0F');
        $this->addSql('DROP INDEX IDX_CAE5226BF65E9B0F ON order_product_options');
        $this->addSql('ALTER TABLE order_product_options DROP order_product_id');
        $this->addSql('ALTER TABLE order_product_options ADD CONSTRAINT FK_CAE5226B4584665A FOREIGN KEY (product_id) REFERENCES order_products (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
