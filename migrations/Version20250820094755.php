<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250820094755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_attribute DROP FOREIGN KEY FK_94DA59764584665A');
        $this->addSql('ALTER TABLE product_attribute DROP FOREIGN KEY FK_94DA59769000C6CB');
        $this->addSql('ALTER TABLE product_attribute DROP FOREIGN KEY FK_94DA5976B6E62EFA');
        $this->addSql('ALTER TABLE product_attribute_group DROP FOREIGN KEY FK_BC73A2EE4584665A');
        $this->addSql('ALTER TABLE product_attribute_group DROP FOREIGN KEY FK_BC73A2EE62D643B7');
        $this->addSql('DROP TABLE product_attribute');
        $this->addSql('DROP TABLE product_attribute_group');
        // Keep all indexes on product_attribute_assignment as per mapping
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_attribute (id INT AUTO_INCREMENT NOT NULL, product_attribute_group_id INT DEFAULT NULL, attribute_id INT NOT NULL, product_id INT DEFAULT NULL, text VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_94DA59764584665A (product_id), INDEX IDX_94DA59769000C6CB (product_attribute_group_id), INDEX IDX_94DA5976B6E62EFA (attribute_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE product_attribute_group (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, attribute_group_id INT DEFAULT NULL, INDEX IDX_BC73A2EE4584665A (product_id), INDEX IDX_BC73A2EE62D643B7 (attribute_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT FK_94DA59764584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT FK_94DA59769000C6CB FOREIGN KEY (product_attribute_group_id) REFERENCES product_attribute_group (id) ON UPDATE NO ACTION');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT FK_94DA5976B6E62EFA FOREIGN KEY (attribute_id) REFERENCES attribute (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE product_attribute_group ADD CONSTRAINT FK_BC73A2EE4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE product_attribute_group ADD CONSTRAINT FK_BC73A2EE62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON UPDATE NO ACTION');
        // Indexes on product_attribute_assignment are kept in up(); no-op here
    }
}
