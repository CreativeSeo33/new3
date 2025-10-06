<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251006130900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.email/is_verified/audit/tokenVersion and create user_refresh_token & user_one_time_token tables';
    }

    public function up(Schema $schema): void
    {
        // users: email + security/audit fields
        $this->addSql("ALTER TABLE users 
            ADD email VARCHAR(180) DEFAULT NULL, 
            ADD is_verified TINYINT(1) NOT NULL DEFAULT 0, 
            ADD failed_login_attempts INT NOT NULL DEFAULT 0, 
            ADD locked_until DATETIME DEFAULT NULL, 
            ADD last_login_at DATETIME DEFAULT NULL, 
            ADD token_version INT NOT NULL DEFAULT 0");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERS_EMAIL ON users (email)');

        // user_refresh_token
        $this->addSql("CREATE TABLE user_refresh_token (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            token_hash VARCHAR(128) NOT NULL,
            salt VARCHAR(64) NOT NULL,
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            rotated_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            ua_hash VARCHAR(64) DEFAULT NULL,
            ip_hash VARCHAR(64) DEFAULT NULL,
            INDEX IDX_URT_USER (user_id),
            INDEX IDX_URT_EXPIRES (expires_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE user_refresh_token ADD CONSTRAINT FK_URT_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        // user_one_time_token
        $this->addSql("CREATE TABLE user_one_time_token (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            type VARCHAR(32) NOT NULL,
            token_hash VARCHAR(128) NOT NULL,
            salt VARCHAR(64) NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_UOTT_TYPE (type),
            INDEX IDX_UOTT_EXPIRES (expires_at),
            INDEX IDX_UOTT_USER (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE user_one_time_token ADD CONSTRAINT FK_UOTT_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Drop token tables
        $this->addSql('ALTER TABLE user_refresh_token DROP FOREIGN KEY FK_URT_USER');
        $this->addSql('ALTER TABLE user_one_time_token DROP FOREIGN KEY FK_UOTT_USER');
        $this->addSql('DROP TABLE user_refresh_token');
        $this->addSql('DROP TABLE user_one_time_token');

        // Revert users
        $this->addSql('DROP INDEX UNIQ_USERS_EMAIL ON users');
        $this->addSql('ALTER TABLE users DROP email, DROP is_verified, DROP failed_login_attempts, DROP locked_until, DROP last_login_at, DROP token_version');
    }
}


