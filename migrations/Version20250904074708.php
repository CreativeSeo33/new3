<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904074708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change cart.id from auto-increment int to ULID for cookie-based cart identification';
    }

    public function up(Schema $schema): void
    {
        // Drop foreign key constraint first
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25271AD5CDBF');

        // Change id columns to ULID
        $this->addSql('ALTER TABLE cart CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\'');
        $this->addSql('ALTER TABLE cart_item CHANGE cart_id cart_id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\'');

        // Recreate foreign key constraint
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraint first
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25271AD5CDBF');

        // Change back to INT with auto increment
        $this->addSql('ALTER TABLE cart CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE cart_item CHANGE cart_id cart_id INT NOT NULL');

        // Recreate foreign key constraint
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id)');
    }
}
