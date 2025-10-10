# Промпт для создания Docker-инфраструктуры проекта

## Задача

Создать production-ready Docker-инфраструктуру для Symfony 7.3 e-commerce проекта с Vue 3 SPA админкой и FSD-каталогом.

## Контекст проекта

### Технологический стек (из анализа)

**Backend:**
- PHP 8.2+
- Symfony 7.3.* (Framework, Security, Doctrine, API Platform, Twig)
- API Platform ^4.1 (REST API, stateless, JWT)
- Doctrine ORM ^3.5, DBAL ^3
- LexikJWTAuthenticationBundle ^3.1
- NelmioCorsBundle
- LiipImagineBundle 2.12.3
- Monolog 3
- Symfony UX Bundle (Stimulus, TwigComponent, LiveComponent)

**Frontend:**
- Node.js (для сборки)
- Vue ^3.5.18 (SPA админка)
- TypeScript ^5.9.2 (strict mode)
- Webpack Encore ^5
- TailwindCSS ^3.4.17
- Vite ^5.4.10 (для Storybook)
- Pinia ^3.0.3, Vue Router ^4.5.1

**База данных:**
- PostgreSQL (рекомендовано) или MySQL/MariaDB
- Миграции через Doctrine Migrations

**Дополнительно:**
- Redis (для кеша сессий, JWT token version, rate limiting)
- Mailer (SMTP для email-верификации и сброса паролей)

### Структура проекта (ключевые пути)

```
/
├── assets/
│   ├── admin/          # Vue 3 SPA (admin.ts — entrypoint)
│   ├── catalog/        # FSD каталог (catalog.ts — entrypoint)
│   ├── controllers/    # Stimulus контроллеры
│   └── styles/
├── bin/
│   └── console
├── config/
│   ├── packages/
│   ├── routes/
│   └── services.yaml
├── migrations/
├── public/
│   ├── build/          # Артефакты Encore (gitignore)
│   ├── img/
│   └── index.php
├── src/
│   ├── Controller/
│   ├── Entity/
│   ├── Repository/
│   ├── Service/
│   └── Kernel.php
├── templates/
├── var/
│   ├── cache/
│   └── log/
├── vendor/             # Composer (gitignore)
├── node_modules/       # npm (gitignore)
├── composer.json
├── composer.lock
├── package.json
├── package-lock.json
├── symfony.lock
├── webpack.config.js
├── tailwind.config.js
├── tsconfig.json
└── .env
```

### Требования к окружению

**PHP расширения (обязательны):**
- pdo_pgsql (или pdo_mysql)
- intl
- opcache
- zip
- xml
- curl
- mbstring
- gd (для LiipImagine)
- apcu (опционально, для кеша)

**Переменные окружения (критичные):**
- `APP_ENV` (prod/dev)
- `APP_SECRET`
- `DATABASE_URL`
- `CORS_ALLOW_ORIGIN`
- `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`
- `MAILER_DSN`
- `REDIS_URL` (опционально)

### Особенности проекта

1. **Stateless API:** все эндпоинты под `^/api` stateless, JWT в httpOnly cookies (`__Host-acc`, `__Host-ref`)
2. **Сборка фронтенда:** через Webpack Encore в `public/build/`
3. **Медиа:** загрузки в `public/media/`, кеш LiipImagine в `public/media/cache/`
4. **Логи:** `var/log/*.log` (dev.log, request_dev.log, doctrine_dev.log, security_dev.log)
5. **Кеш:** `var/cache/{dev,prod}/`
6. **Сессии:** рекомендовано Redis (для multi-instance)
7. **Rate Limiting:** встроенный Symfony RateLimiter (auth endpoints)
8. **Healthcheck:** нужен эндпоинт `/health` (создать в контроллере)

---

## Требования к Docker-инфраструктуре

### 1. Multi-stage Dockerfile для PHP-приложения

**Стадии:**
1. **Base:** базовый образ с PHP 8.2-fpm Alpine
2. **Composer:** установка PHP-зависимостей
3. **Node:** сборка фронтенда (Webpack Encore)
4. **Production:** финальный образ с артефактами

**Требования к стадиям:**

**Base стадия:**
- PHP 8.2-fpm-alpine
- Установить расширения: pdo_pgsql, intl, opcache, zip, xml, curl, mbstring, gd, apcu
- При установке расширений НЕ использовать `-j$(nproc)` - это вызывает синтаксические ошибки в Alpine sh
- Оптимизировать opcache для production
- Создать пользователя `www-data` с корректными UID/GID (1000:1000)
- Настроить php.ini (memory_limit=512M, upload_max_filesize=20M, post_max_size=20M)

**Composer стадия:**
- Использовать официальный `composer:2` образ
- `COPY composer.json composer.lock symfony.lock ./`
- `composer install --no-dev --no-scripts --no-progress --no-interaction --prefer-dist`
- Использовать cache mount для ускорения: `--mount=type=cache,target=/root/.composer`
- Игнорировать dev-зависимости в production

**Node стадия:**
- Node.js 20 Alpine
- `COPY package.json ./` (НЕ копировать package-lock.json - он может содержать Windows-специфичные пакеты)
- `COPY --from=composer /app/vendor ./vendor` (КРИТИЧНО: UX-пакеты Symfony ссылаются на file:vendor/...)
- `npm install --no-audit --no-fund --force` (--force игнорирует платформенные ограничения)
- `COPY webpack.config.js postcss.config.js tailwind.config.js tsconfig*.json ./`
- `COPY assets/ themes/ public/ ./` 
- `npm run build` (сборка admin + catalog)
- Артефакты в `public/build/`

**Production стадия:**
- Скопировать vendor/ из Composer стадии
- Скопировать public/build/ из Node стадии
- Скопировать исходники проекта (src/, config/, templates/, public/, bin/, migrations/)
- Выполнить `php bin/console cache:warmup --env=prod`
- Настроить права доступа (var/, public/media/ — writable для www-data)
- EXPOSE 9000 (PHP-FPM)
- CMD ["php-fpm"]

### 2. Nginx образ

**Требования:**
- Nginx Alpine
- Конфигурация для Symfony (index.php как фронт-контроллер)
- Обработка статики из public/ (build/, img/, media/)
- Проксирование PHP-запросов на php:9000
- Gzip сжатие
- Кеш статики (max-age для CSS/JS/images)
- Security headers (X-Frame-Options, X-Content-Type-Options)
- HTTPS redirect (опционально, через reverse proxy)
- Client max body size 20M (для загрузки изображений)

### 3. docker-compose.yml (orchestration)

**Сервисы:**

**php:**
- Build из Dockerfile
- Volumes:
  - `./var:/app/var` (логи, кеш — только для dev; в prod — emptyDir или volumes)
  - `./public/media:/app/public/media` (persistent для загрузок)
- Environment из `.env.docker`
- Depends on: db, redis
- Healthcheck: `php bin/console --version || exit 1`
- Restart: unless-stopped

**nginx:**
- Build из Dockerfile.nginx
- Ports: `80:80`, `443:443` (опционально)
- Volumes:
  - `./public:/app/public:ro` (статика read-only)
- Depends on: php
- Healthcheck: `curl -f http://localhost/health || exit 1`
- Restart: unless-stopped

**db (PostgreSQL):**
- Image: postgres:16-alpine
- Environment:
  - POSTGRES_DB
  - POSTGRES_USER
  - POSTGRES_PASSWORD
- Volumes: `postgres_data:/var/lib/postgresql/data`
- Healthcheck: `pg_isready -U ${POSTGRES_USER}`
- Restart: unless-stopped

**redis (опционально, рекомендовано):**
- Image: redis:7-alpine
- Command: `redis-server --maxmemory 256mb --maxmemory-policy allkeys-lru`
- Volumes: `redis_data:/data`
- Healthcheck: `redis-cli ping || exit 1`
- Restart: unless-stopped

**mailhog (для dev окружения):**
- Image: mailhog/mailhog
- Ports: `1025:1025`, `8025:8025`
- Environment: MH_STORAGE=memory

### 4. Дополнительные файлы

**Dockerfile.nginx:**
- Конфигурация nginx для Symfony
- Шаблон с подстановкой переменных (fastcgi_pass, server_name)

**.dockerignore:**
```
var/
vendor/
node_modules/
public/build/
public/media/cache/
.env.local
.git/
.idea/
*.md
tests/
.vscode/
.cursor/
package-lock.json
```

**Важно:** `package-lock.json` в .dockerignore, т.к. он содержит Windows-специфичные пакеты при разработке на Windows

**.env.docker (пример для docker-compose):**
```dotenv
APP_ENV=prod
APP_SECRET=CHANGE_ME
DATABASE_URL=postgresql://app:password@db:5432/app_db
REDIS_URL=redis://redis:6379
CORS_ALLOW_ORIGIN=https://example.com
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=CHANGE_ME
MAILER_DSN=smtp://mailhog:1025
```

**Makefile (опционально, для удобства):**
```makefile
build:
	docker-compose build

up:
	docker-compose up -d

down:
	docker-compose down

logs:
	docker-compose logs -f

migrate:
	docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

fixtures:
	docker-compose exec php php bin/console doctrine:fixtures:load --no-interaction

cache-clear:
	docker-compose exec php php bin/console cache:clear

restart:
	docker-compose restart php nginx
```

---

## Критерии приёмки Docker-инфраструктуры

### Функциональные:
- [ ] Контейнеры запускаются без ошибок (`docker-compose up -d`)
- [ ] Symfony доступен по `http://localhost` (nginx → php-fpm)
- [ ] Миграции применяются (`docker-compose exec php bin/console doctrine:migrations:migrate`)
- [ ] Статика отдаётся корректно (CSS/JS из public/build/)
- [ ] Загрузка изображений работает (public/media/ writable)
- [ ] API эндпоинты отвечают (GET /api, POST /api/cart/items)
- [ ] JWT-аутентификация работает (cookies `__Host-acc`)
- [ ] Healthcheck проходит (`/health` endpoint возвращает 200)

### Производительность:
- [ ] Размер финального PHP образа < 200MB (Alpine + оптимизация)
- [ ] Opcache включён и настроен (interned_strings_buffer=16, max_accelerated_files=20000)
- [ ] docker-php-ext-install БЕЗ флага `-j$(nproc)` (вызывает ошибки в Alpine)
- [ ] Статика кешируется на стороне Nginx (Cache-Control headers)
- [ ] Gzip включён для текстовых файлов
- [ ] PHP-FPM настроен (pm=dynamic, pm.max_children=20)

### Безопасность:
- [ ] Нет секретов в образе (через ENV или secrets)
- [ ] Контейнеры не запускаются от root (USER www-data)
- [ ] JWT ключи не в репозитории (генерировать при деплое)
- [ ] CORS настроен через ENV переменную
- [ ] Security headers в Nginx (X-Frame-Options: DENY, CSP)
- [ ] PostgreSQL доступна только внутри сети Docker

### DevOps:
- [ ] `.dockerignore` исключает лишнее
- [ ] Volumes для persistent данных (DB, Redis, media)
- [ ] Логи доступны (`docker-compose logs php`)
- [ ] Graceful shutdown (SIGTERM обрабатывается)
- [ ] Restart политика настроена (unless-stopped)

---

## Дополнительные рекомендации

### 1. JWT ключи
Генерировать при первом запуске:
```bash
docker-compose exec php sh -c '
  mkdir -p config/jwt
  openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096
  openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
  chmod 644 config/jwt/private.pem config/jwt/public.pem
'
```

### 2. Production deployment checklist
- [ ] `APP_ENV=prod`, `APP_DEBUG=0`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] Секреты через Docker secrets или HashiCorp Vault
- [ ] Reverse proxy (Traefik/nginx) с SSL termination
- [ ] Backup стратегия для PostgreSQL и media/
- [ ] Мониторинг (Prometheus, Grafana, Loki)
- [ ] Логи в stdout/stderr (для aggregation)
- [ ] Autoscaling PHP pods (Kubernetes HPA)

### 3. Development окружения
Создать `docker-compose.dev.yml` override:
```yaml
services:
  php:
    environment:
      APP_ENV: dev
      APP_DEBUG: 1
    volumes:
      - ./:/app
    command: php-fpm -F -R
  
  mailhog:
    ports:
      - "8025:8025"
```

Запуск: `docker-compose -f docker-compose.yml -f docker-compose.dev.yml up`

### 4. CI/CD integration
- Сборка образов в CI (GitHub Actions, GitLab CI)
- Push в registry (Docker Hub, AWS ECR, GitLab Registry)
- Автотесты в контейнерах (`docker-compose run php vendor/bin/phpunit`)
- Deployment через ArgoCD или Helm (для Kubernetes)

---

## Финальный чеклист выполнения

Создать файлы:
1. `Dockerfile` (multi-stage: base, composer, node, production)
2. `Dockerfile.nginx` (nginx + Symfony конфигурация)
3. `docker-compose.yml` (orchestration: php, nginx, db, redis)
4. `.dockerignore` (исключения для build context)
5. `.env.docker.example` (шаблон переменных окружения)
6. `Makefile` (опционально, для удобства)
7. `docs/docker-README.md` (инструкции по запуску и деплою)

Проверить:
- [ ] `docker-compose build` проходит без ошибок
- [ ] `docker-compose up -d` запускает все сервисы
- [ ] `docker-compose exec php bin/console --version` работает
- [ ] `docker-compose exec php bin/console doctrine:migrations:migrate` работает
- [ ] `curl http://localhost` возвращает HTML страницу
- [ ] `curl http://localhost/api` возвращает JSON (API Platform)
- [ ] Логи доступны: `docker-compose logs -f php`
- [ ] Healthchecks проходят: `docker ps` показывает "healthy"

---

## Важные замечания из проекта

1. **Без хардкодов:** все URL, пути, константы через ENV/parameters (`config/services.yaml`)
2. **Stateless API:** сессии в Redis, JWT в cookies
3. **CORS:** настроить origin из ENV `CORS_ALLOW_ORIGIN`
4. **Rate Limiting:** работает out-of-box через Symfony RateLimiter (в памяти; для кластера — Redis storage)
5. **Media uploads:** `public/media/` должна быть persistent volume
6. **Миграции:** запускать вручную при деплое (`doctrine:migrations:migrate`)
7. **Кеш:** `var/cache/prod/` можно в emptyDir (генерируется при старте)
8. **Логи:** писать в stdout/stderr для агрегации (настроить Monolog)
9. **Windows → Linux:** при разработке на Windows не копировать `package-lock.json` в образ - использовать только `package.json`
10. **Symfony UX:** vendor должен быть доступен ДО npm install, т.к. пакеты UX ссылаются на `file:vendor/...`

---

## Пример команд после создания инфраструктуры

```bash
# Сборка образов
docker-compose build

# Запуск окружения
docker-compose up -d

# Генерация JWT ключей (первый раз)
docker-compose exec php sh -c 'mkdir -p config/jwt && \
  openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 && \
  openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem'

# Применение миграций
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Прогрев кеша
docker-compose exec php php bin/console cache:warmup --env=prod

# Проверка здоровья
curl http://localhost/health

# Просмотр логов
docker-compose logs -f php nginx

# Остановка
docker-compose down
```

---

## Известные проблемы и решения

### 1. Ошибка "syntax error: unexpected (" при установке PHP расширений
**Проблема:** `/bin/sh: syntax error: unexpected "("` на строке с `docker-php-ext-install -j$(nproc)`

**Причина:** Alpine sh не поддерживает command substitution `$(...)` в некоторых контекстах RUN

**Решение:** Убрать флаг `-j` полностью:
```dockerfile
# ❌ НЕ работает
RUN docker-php-ext-install -j$(nproc) intl pdo pdo_pgsql

# ✅ Работает
RUN docker-php-ext-install intl pdo pdo_pgsql
```

### 2. Ошибка "EBADPLATFORM" для @rollup/rollup-win32-x64-msvc
**Проблема:** `npm error notsup Unsupported platform for @rollup/rollup-win32-x64-msvc: wanted {"os":"win32"} (current: {"os":"linux"})`

**Причина:** `package-lock.json` создан на Windows и содержит Windows-специфичные пакеты

**Решение:**
1. НЕ копировать `package-lock.json` в Dockerfile
2. Добавить `package-lock.json` в `.dockerignore`
3. Использовать `npm install --force` вместо `npm ci`

### 3. Ошибка "@symfony/ux-live-component/package.json could not be found"
**Проблема:** Webpack не находит UX-пакеты при сборке

**Причина:** В `package.json` UX-пакеты ссылаются на `file:vendor/symfony/ux-*/assets`, но vendor появляется только после composer

**Решение:** Копировать vendor в Node стадию ДО npm install:
```dockerfile
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json ./
COPY --from=composer /app/vendor ./vendor  # ← Критично!
RUN npm install --no-audit --no-fund --force
```

---

## Связанные правила проекта

- `@hardcoderules.mdc` — запрет хардкодов (использовать ENV)
- `@projectrules.mdc` — архитектурные инварианты
- `docs/AI_CONTEXT.md` — технологический стек и версии
- `docs/customer_auth_system.md` — настройка JWT и cookies
- `config/services.yaml` — параметры приложения

---

## Формат результата

Создать PR с файлами:
- `Dockerfile`
- `Dockerfile.nginx`
- `docker-compose.yml`
- `.dockerignore`
- `.env.docker.example`
- `Makefile`
- `docs/docker-README.md`

В описании PR указать:
- Версии образов (PHP, Node, PostgreSQL, Redis)
- Размер финального образа
- Результаты smoke-тестов (curl'ы к эндпоинтам)
- Инструкции по first-run setup

---

**Цель:** Production-ready Docker-инфраструктура, готовая к деплою в Kubernetes или Docker Swarm, с оптимизацией размера, безопасностью и удобством разработки.


