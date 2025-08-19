<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250819144205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute DROP FOREIGN KEY FK_FA7AEFFB62D643B7');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFB62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE product_attribute DROP FOREIGN KEY FK_94DA59769000C6CB');
        $this->addSql('ALTER TABLE product_attribute CHANGE attribute_id attribute_id INT NOT NULL');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT FK_94DA59769000C6CB FOREIGN KEY (product_attribute_group_id) REFERENCES product_attribute_group (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE product_attribute_group DROP FOREIGN KEY FK_BC73A2EE62D643B7');
        $this->addSql('ALTER TABLE product_attribute_group ADD CONSTRAINT FK_BC73A2EE62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute DROP FOREIGN KEY FK_FA7AEFFB62D643B7');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFB62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_attribute DROP FOREIGN KEY FK_94DA59769000C6CB');
        $this->addSql('ALTER TABLE product_attribute CHANGE attribute_id attribute_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT FK_94DA59769000C6CB FOREIGN KEY (product_attribute_group_id) REFERENCES product_attribute_group (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_attribute_group DROP FOREIGN KEY FK_BC73A2EE62D643B7');
        $this->addSql('ALTER TABLE product_attribute_group ADD CONSTRAINT FK_BC73A2EE62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON UPDATE NO ACTION ON DELETE SET NULL');
    }
}
