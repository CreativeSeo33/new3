<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250813152559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute ADD attribute_group_id INT DEFAULT NULL, ADD sort_order INT DEFAULT NULL, ADD show_in_category TINYINT(1) DEFAULT NULL, ADD short_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFB62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id)');
        $this->addSql('CREATE INDEX IDX_FA7AEFFB62D643B7 ON attribute (attribute_group_id)');
        $this->addSql('ALTER TABLE attribute_group ADD sort_order INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute DROP FOREIGN KEY FK_FA7AEFFB62D643B7');
        $this->addSql('DROP INDEX IDX_FA7AEFFB62D643B7 ON attribute');
        $this->addSql('ALTER TABLE attribute DROP attribute_group_id, DROP sort_order, DROP show_in_category, DROP short_name');
        $this->addSql('ALTER TABLE attribute_group DROP sort_order');
    }
}
