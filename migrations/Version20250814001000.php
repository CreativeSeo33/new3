<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250814001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adjust FKs for product attribute entities: product_attribute_group.attribute_group SET NULL, product_attribute.product_attribute_group CASCADE';
    }

    public function up(Schema $schema): void
    {
        // product_attribute_group.attribute_group ON DELETE SET NULL
        $this->addSql('ALTER TABLE product_attribute_group DROP FOREIGN KEY FK_BC73A2EE62D643B7');
        $this->addSql('ALTER TABLE product_attribute_group ADD CONSTRAINT FK_BC73A2EE62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON DELETE SET NULL');

        // product_attribute.product_attribute_group ON DELETE CASCADE
        $this->addSql('ALTER TABLE product_attribute DROP FOREIGN KEY FK_94DA59769000C6CB');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT FK_94DA59769000C6CB FOREIGN KEY (product_attribute_group_id) REFERENCES product_attribute_group (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_attribute_group DROP FOREIGN KEY FK_BC73A2EE62D643B7');
        $this->addSql('ALTER TABLE product_attribute_group ADD CONSTRAINT FK_BC73A2EE62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id)');

        $this->addSql('ALTER TABLE product_attribute DROP FOREIGN KEY FK_94DA59769000C6CB');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT FK_94DA59769000C6CB FOREIGN KEY (product_attribute_group_id) REFERENCES product_attribute_group (id)');
    }
}


