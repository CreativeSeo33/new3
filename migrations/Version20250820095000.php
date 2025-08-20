<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250820095000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing indexes and unique constraint for product_attribute_assignment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uq_paa_product_attr_pos ON product_attribute_assignment (product_id, attribute_id, position)');
        $this->addSql('CREATE INDEX idx_paa_int ON product_attribute_assignment (int_value)');
        $this->addSql('CREATE INDEX idx_paa_decimal ON product_attribute_assignment (decimal_value)');
        $this->addSql('CREATE INDEX idx_paa_bool ON product_attribute_assignment (bool_value)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uq_paa_product_attr_pos ON product_attribute_assignment');
        $this->addSql('DROP INDEX idx_paa_int ON product_attribute_assignment');
        $this->addSql('DROP INDEX idx_paa_decimal ON product_attribute_assignment');
        $this->addSql('DROP INDEX idx_paa_bool ON product_attribute_assignment');
    }
}


