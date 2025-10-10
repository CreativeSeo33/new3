# Docker: команды для разработки (Windows PowerShell)

## Быстрый старт

```powershell
# Сборка образов (php, nginx, assets внутри Dockerfile)
docker-compose build

# Запуск сервисов в фоне
docker-compose up -d

# Проверка, что PHP и Symfony доступны внутри контейнера
docker-compose exec php php -v
docker-compose exec php php bin/console --version
```

## Первичная инициализация

```powershell
# Генерация JWT ключей (хранятся в ./config/jwt)
docker-compose exec php sh -lc "mkdir -p config/jwt && openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 && openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem && chmod 640 config/jwt/private.pem config/jwt/public.pem"

# Применить миграции Doctrine
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

## Жизненный цикл контейнеров

```powershell
# Старт / стоп / статус
docker-compose up -d
docker-compose down
docker-compose ps

# Перезапуск ключевых сервисов
docker-compose restart php nginx

# Полная остановка с удалением volume'ов (ОСТОРОЖНО: сотрёт БД)
docker-compose down -v
```

## Логи и диагностика

```powershell
# Общие логи всех сервисов
docker-compose logs -f

# Точечно
docker-compose logs -f php
docker-compose logs -f nginx
docker-compose logs -f db

# Логи Symfony внутри контейнера PHP
docker-compose exec php sh -lc "tail -f var/log/dev.log"
docker-compose exec php sh -lc "tail -f var/log/request_dev.log"
docker-compose exec php sh -lc "tail -f var/log/doctrine_dev.log"
```

## Symfony консоль (частые команды)

```powershell
# Кеш
docker-compose exec php php bin/console cache:clear

docker-compose exec php php bin/console cache:warmup --env=prod

# Doctrine
docker-compose exec php php bin/console doctrine:schema:validate
```

## Доступ в контейнеры (shell)

```powershell
docker-compose exec php sh
docker-compose exec nginx sh
docker-compose exec db sh
```

## База данных (MySQL 8)

По умолчанию: база `${MYSQL_DATABASE:-new3}`, пароль рута `${MYSQL_ROOT_PASSWORD:-password}` (см. docker-compose.yml).

```powershell
# Проверка соединения из контейнера БД
docker-compose exec db sh -lc 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SHOW DATABASES"'

# Импорт локального дампа new3.sql в текущую БД (PowerShell)
Get-Content .\new3.sql -Raw | docker-compose exec -T db sh -lc 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "${MYSQL_DATABASE:-new3}"'
```

## Полезные URL

- http://localhost — фронт
- http://localhost/api — API Platform
- http://localhost/health — healthcheck
- http://localhost:8025 — MailHog UI (SMTP: 1025)

## Пересборка с обновлением фронта

```powershell
# После изменений в assets/ пересобрать образ PHP (включает assets stage)
docker-compose build php

docker-compose up -d php nginx
```

## Альтернатива через Makefile (если установлен make)

```powershell
make build
make up
make down
make logs
make migrate
make fixtures
make cache-clear
make warmup
make restart
make jwt-keys
```

Примечания:
- Сборка фронта (admin/catalog) выполняется в стадии `assets` внутри Dockerfile и попадает в `public/build` автоматически при `docker-compose build`.
- Данные БД и media лежат в volume'ах (`mysql_data`, `public/media`). Команда `down -v` удаляет БД.
