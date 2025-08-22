<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250822100150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cart_item_option_assignment (cart_item_id INT NOT NULL, product_option_value_assignment_id INT NOT NULL, INDEX IDX_9CAE0419E9B59A59 (cart_item_id), INDEX IDX_9CAE04194859DA71 (product_option_value_assignment_id), PRIMARY KEY(cart_item_id, product_option_value_assignment_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cart_item_option_assignment ADD CONSTRAINT FK_9CAE0419E9B59A59 FOREIGN KEY (cart_item_id) REFERENCES cart_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cart_item_option_assignment ADD CONSTRAINT FK_9CAE04194859DA71 FOREIGN KEY (product_option_value_assignment_id) REFERENCES product_option_value_assignment (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX uniq_cart_product ON cart_item');
        $this->addSql('ALTER TABLE cart_item ADD options_price_modifier INT DEFAULT 0 NOT NULL, ADD effective_unit_price INT DEFAULT 0 NOT NULL, ADD options_hash VARCHAR(32) DEFAULT NULL, ADD selected_options_data JSON DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_product_options ON cart_item (cart_id, product_id, options_hash)');
        $this->addSql('DROP INDEX idx_paa_bool ON product_attribute_assignment');
        $this->addSql('DROP INDEX uq_paa_product_attr_pos ON product_attribute_assignment');
        $this->addSql('DROP INDEX idx_paa_int ON product_attribute_assignment');
        $this->addSql('DROP INDEX idx_paa_decimal ON product_attribute_assignment');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_item_option_assignment DROP FOREIGN KEY FK_9CAE0419E9B59A59');
        $this->addSql('ALTER TABLE cart_item_option_assignment DROP FOREIGN KEY FK_9CAE04194859DA71');
        $this->addSql('DROP TABLE cart_item_option_assignment');
        $this->addSql('DROP INDEX uniq_cart_product_options ON cart_item');
        $this->addSql('ALTER TABLE cart_item DROP options_price_modifier, DROP effective_unit_price, DROP options_hash, DROP selected_options_data');
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_product ON cart_item (cart_id, product_id)');
        $this->addSql('CREATE INDEX idx_paa_bool ON product_attribute_assignment (bool_value)');
        $this->addSql('CREATE UNIQUE INDEX uq_paa_product_attr_pos ON product_attribute_assignment (product_id, attribute_id, position)');
        $this->addSql('CREATE INDEX idx_paa_int ON product_attribute_assignment (int_value)');
        $this->addSql('CREATE INDEX idx_paa_decimal ON product_attribute_assignment (decimal_value)');
    }
}
