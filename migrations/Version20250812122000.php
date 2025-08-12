<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add secondary indexes for carousel, category and product_image tables.
 */
final class Version20250812122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes: carousel(place), carousel(sort), category(name), product_image(sort_order).';
    }

    public function up(Schema $schema): void
    {
        // carousel
        $this->addSql('CREATE INDEX carousel_place_idx ON carousel (place)');
        $this->addSql('CREATE INDEX carousel_sort_idx ON carousel (sort)');

        // category
        $this->addSql('CREATE INDEX category_name_idx ON category (name)');

        // product_image
        $this->addSql('CREATE INDEX product_image_sort_idx ON product_image (sort_order)');
    }

    public function down(Schema $schema): void
    {
        // product_image
        $this->addSql('DROP INDEX product_image_sort_idx ON product_image');

        // category
        $this->addSql('DROP INDEX category_name_idx ON category');

        // carousel
        $this->addSql('DROP INDEX carousel_sort_idx ON carousel');
        $this->addSql('DROP INDEX carousel_place_idx ON carousel');
    }
}


