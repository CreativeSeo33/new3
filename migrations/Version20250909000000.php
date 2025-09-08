<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Миграция для заполнения токенов в существующих гостевых корзинах
 *
 * Заполняет поле token UUID для всех активных гостевых корзин,
 * где token пустой (для поддержки миграции с ULID на opaque token)
 */
final class Version20250909000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fill tokens for existing guest carts (ULID to opaque token migration)';
    }

    public function up(Schema $schema): void
    {
        // Заполняем токены для гостевых корзин (user_id IS NULL или user_id = 0)
        // Используем UUID() функцию MySQL для генерации уникальных токенов
        $this->addSql("
            UPDATE cart
            SET token = UUID()
            WHERE token IS NULL
              AND (user_id IS NULL OR user_id = 0)
              AND (expires_at IS NULL OR expires_at > NOW())
        ");

        // Проверяем, что все гостевые корзины имеют токены
        $this->addSql("
            SELECT COUNT(*) as guest_carts_without_token
            FROM cart
            WHERE token IS NULL
              AND (user_id IS NULL OR user_id = 0)
              AND (expires_at IS NULL OR expires_at > NOW())
        ");
    }

    public function down(Schema $schema): void
    {
        // Откат: очищаем токены только у гостевых корзин
        // (пользовательские корзины могли получить токены после присвоения)
        $this->addSql("
            UPDATE cart
            SET token = NULL
            WHERE user_id IS NULL OR user_id = 0
        ");
    }
}
