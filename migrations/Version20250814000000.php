<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250814000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set ON DELETE SET NULL for attribute.attribute_group_id and add missing index if needed';
    }

    public function up(Schema $schema): void
    {
        // MySQL требует перебор: надо дропнуть FK и создать заново с ON DELETE SET NULL
        $this->addSql('ALTER TABLE attribute DROP FOREIGN KEY FK_FA7AEFFB62D643B7');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFB62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute DROP FOREIGN KEY FK_FA7AEFFB62D643B7');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFB62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id)');
    }
}


