<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251006152100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facet_dictionary DROP FOREIGN KEY FK_FACET_DICTIONARY_CATEGORY');
        $this->addSql('DROP TABLE facet_dictionary');
        $this->addSql('DROP INDEX UNIQ_FC_CATEGORY ON facet_config');
        $this->addSql('DROP INDEX IDX_FC_SCOPE ON facet_config');
        $this->addSql('ALTER TABLE facet_config RENAME INDEX idx_fc_category TO IDX_990BB50C12469DE2');
        $this->addSql('ALTER TABLE `order` ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F5299398A76ED395 ON `order` (user_id)');
        $this->addSql('DROP INDEX idx_paa_string ON product_attribute_assignment');
        $this->addSql('DROP INDEX idx_pova_product ON product_option_value_assignment');
        $this->addSql('DROP INDEX idx_pova_product_option ON product_option_value_assignment');
        $this->addSql('DROP INDEX idx_ptc_category_product ON product_to_category');
        $this->addSql('ALTER TABLE user_one_time_token CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_one_time_token RENAME INDEX idx_uott_user TO IDX_361A5164A76ED395');
        $this->addSql('ALTER TABLE user_one_time_token RENAME INDEX idx_uott_type TO IDX_361A51648CDE5729');
        $this->addSql('ALTER TABLE user_one_time_token RENAME INDEX idx_uott_expires TO IDX_361A5164F9D83E2');
        $this->addSql('ALTER TABLE user_refresh_token CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE rotated_at rotated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_refresh_token RENAME INDEX idx_urt_user TO IDX_29C18CC5A76ED395');
        $this->addSql('ALTER TABLE user_refresh_token RENAME INDEX idx_urt_expires TO IDX_29C18CC5F9D83E2');
        $this->addSql('ALTER TABLE users CHANGE locked_until locked_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE last_login_at last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE users RENAME INDEX uniq_users_email TO UNIQ_1483A5E9E7927C74');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE facet_dictionary (id INT AUTO_INCREMENT NOT NULL, category_id INT DEFAULT NULL, attributes_json JSON DEFAULT NULL, options_json JSON DEFAULT NULL, price_min INT DEFAULT NULL, price_max INT DEFAULT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FD_CATEGORY (category_id), INDEX IDX_FD_CATEGORY (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE facet_dictionary ADD CONSTRAINT FK_FACET_DICTIONARY_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395');
        $this->addSql('DROP INDEX IDX_F5299398A76ED395 ON `order`');
        $this->addSql('ALTER TABLE `order` DROP user_id');
        $this->addSql('CREATE INDEX idx_pova_product ON product_option_value_assignment (product_id)');
        $this->addSql('CREATE INDEX idx_pova_product_option ON product_option_value_assignment (product_id, option_id)');
        $this->addSql('CREATE INDEX idx_ptc_category_product ON product_to_category (category_id, product_id)');
        $this->addSql('CREATE INDEX idx_paa_string ON product_attribute_assignment (string_value)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FC_CATEGORY ON facet_config (category_id)');
        $this->addSql('CREATE INDEX IDX_FC_SCOPE ON facet_config (scope)');
        $this->addSql('ALTER TABLE facet_config RENAME INDEX idx_990bb50c12469de2 TO IDX_FC_CATEGORY');
        $this->addSql('ALTER TABLE users CHANGE locked_until locked_until DATETIME DEFAULT NULL, CHANGE last_login_at last_login_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE users RENAME INDEX uniq_1483a5e9e7927c74 TO UNIQ_USERS_EMAIL');
        $this->addSql('ALTER TABLE user_refresh_token CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE rotated_at rotated_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user_refresh_token RENAME INDEX idx_29c18cc5a76ed395 TO IDX_URT_USER');
        $this->addSql('ALTER TABLE user_refresh_token RENAME INDEX idx_29c18cc5f9d83e2 TO IDX_URT_EXPIRES');
        $this->addSql('ALTER TABLE user_one_time_token CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user_one_time_token RENAME INDEX idx_361a51648cde5729 TO IDX_UOTT_TYPE');
        $this->addSql('ALTER TABLE user_one_time_token RENAME INDEX idx_361a5164f9d83e2 TO IDX_UOTT_EXPIRES');
        $this->addSql('ALTER TABLE user_one_time_token RENAME INDEX idx_361a5164a76ed395 TO IDX_UOTT_USER');
    }
}
