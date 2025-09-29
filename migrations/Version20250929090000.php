<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250929090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facet dictionary table and supporting indexes for facet performance.';
    }

    public function up(Schema $schema): void
    {
        // facet_dictionary
        $this->addSql("CREATE TABLE facet_dictionary (id INT AUTO_INCREMENT NOT NULL, category_id INT DEFAULT NULL, attributes_json JSON DEFAULT NULL, options_json JSON DEFAULT NULL, price_min INT DEFAULT NULL, price_max INT DEFAULT NULL, updated_at DATETIME NOT NULL, INDEX IDX_FD_CATEGORY (category_id), UNIQUE INDEX UNIQ_FD_CATEGORY (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE facet_dictionary ADD CONSTRAINT FK_FACET_DICTIONARY_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');

        // Indexes for product_option_value_assignment
        $this->addSql('CREATE INDEX idx_pova_product ON product_option_value_assignment (product_id)');
        $this->addSql('CREATE INDEX idx_pova_product_option ON product_option_value_assignment (product_id, option_id)');

        // Composite index for product_to_category
        $this->addSql('CREATE INDEX idx_ptc_category_product ON product_to_category (category_id, product_id)');

        // String value index for product_attribute_assignment (admin search)
        $this->addSql('CREATE INDEX idx_paa_string ON product_attribute_assignment (string_value)');
    }

    public function down(Schema $schema): void
    {
        // Drop added indexes
        $this->addSql('DROP INDEX idx_paa_string ON product_attribute_assignment');
        $this->addSql('DROP INDEX idx_ptc_category_product ON product_to_category');
        $this->addSql('DROP INDEX idx_pova_product_option ON product_option_value_assignment');
        $this->addSql('DROP INDEX idx_pova_product ON product_option_value_assignment');

        // Drop facet_dictionary
        $this->addSql('ALTER TABLE facet_dictionary DROP FOREIGN KEY FK_FACET_DICTIONARY_CATEGORY');
        $this->addSql('DROP TABLE facet_dictionary');
    }
}


