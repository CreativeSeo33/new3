<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250820095500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy tables product_attribute and product_attribute_group';
    }

    public function up(Schema $schema): void
    {
        // Drop FKs if exist, then drop tables (MySQL will ignore if already gone)
        $this->addSql('DROP TABLE IF EXISTS product_attribute');
        $this->addSql('DROP TABLE IF EXISTS product_attribute_group');
    }

    public function down(Schema $schema): void
    {
        // No-op rollback (legacy tables are not recreated)
    }
}


