## Обзор проекта

- Symfony 7 (7.3.*) + API Platform ^4.1 для REST API и бэкенд‑бизнес‑логики
- Doctrine ORM ^3.5/DBAL ^3, миграции Doctrine
- Frontend: Vue 3 (SPA для админки) + TailwindCSS + TypeScript (strict)
- Сборка: Webpack Encore (production/dev); Storybook 8 для UI
- Каталог (витрина) — модульная архитектура FSD (`assets/catalog/src`)
- Админка — SPA на Vue 3, роутинг только через Vue Router (`/admin`)

## Технологический стек и версии

- PHP >= 8.2
- Symfony: 7.3.* (FrameworkBundle, Security, Twig, Validator, Serializer, Lock, Monolog, UX пакеты)
- API Platform: ^4.1; форматы: jsonld, json, html; patch: `application/merge-patch+json`; `stateless: true`
- Doctrine: ORM ^3.5, DBAL ^3; Migrations Bundle ^3.4
- Auth: LexikJWTAuthenticationBundle ^3.1 (присутствует), основная аутентификация/ACL — `security.yaml`
- CORS: NelmioCorsBundle (источник из ENV `%env(CORS_ALLOW_ORIGIN)%`)
- Медиа: LiipImagineBundle 2.12.3
- Frontend: Vue ^3.5.18, Vue Router ^4.5.1, Pinia ^3.0.3, TypeScript ^5.9.2
- CSS: TailwindCSS ^3.4.17 (PostCSS, autoprefixer)
- Сборка: Webpack/Encore ^5 (Vue Loader включён для `admin`); Vite ^5 для Storybook окружения

## Сборка и артефакты

- Точки входа Encore:
  - `admin`: `assets/admin/admin.ts`
  - `catalog`: `assets/catalog/catalog.ts`
- Команды (выполнять из корня):
  - `npm run build` — полная сборка
  - `npm run build:admin`, `npm run build:catalog` — таргетированные сборки
  - `npm run dev:*`/`watch` — режим разработки
- Storybook: каталоги вывода `public/storybook-admin`, `public/storybook-catalog`

## Архитектура фронтенда

### Admin (SPA)
- Роутер: `createWebHistory('/admin')` в `assets/admin/router/index.ts`
- Новые admin‑страницы добавлять только через Vue Router (не через Symfony маршруты)
- Стейт/сервисы/репозитории в `assets/admin/*` (components, composables, repositories, services, stores, views)

### Catalog (FSD)
- Базовая структура: `assets/catalog/src/{shared,features,widgets,entities,pages}`
- Алиасы (TS/Webpack): `@` → `assets/catalog/src`, `@shared`, `@features`, `@entities`, `@widgets`, `@pages`, `@admin`
- Шаблоны и генератор модулей: `docs/templates/*`, `docs/generate-module.js`

### TailwindCSS
- `darkMode: 'class'`
- Сканирование контента: `templates/**/*.html.twig`, `assets/admin/**/*.{js,vue,ts}`, `assets/catalog/**/*.{js,vue,ts}`
- Расширенная тема (семантические токены, палитры, safelist для статусов и col-span)

## Архитектура бэкенда

- Bundles: Framework, WebpackEncore, Twig, Security, Doctrine, Migrations, NelmioCors, ApiPlatform, Monolog, Debug/WebProfiler, LexikJWT, LiipImagine, UX (Stimulus, TwigComponent, LiveComponent, Turbo)
- Роутинг: контроллеры по атрибутам (`config/routes.yaml`); `api_login: /api/login`
- Security (`config/packages/security.yaml`):
  - `^/api` — `security: false` (публичный слой API Platform)
  - `^/admin` — доступ `ROLE_ADMIN`
  - `form_login` (`/login`), `logout` → редирект на `app_login`
- CORS (`config/packages/nelmio_cors.yaml`): `allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']`, методы GET/OPTIONS/POST/PUT/PATCH/DELETE
- Конфигурация через ENV/параметры в `config/services.yaml`; запрещено хардкодить URL, пути, бизнес‑константы

## API Platform и пагинация

- Глобальные defaults (`config/packages/api_platform.yaml`):
  - `pagination_items_per_page: %pagination.default_items_per_page%`
  - `pagination_maximum_items_per_page: %pagination.max_items_per_page%`
  - `pagination_client_items_per_page: true`, `pagination_client_page: true`
- Параметры (`config/packages/pagination.yaml`):
  - Общие: `pagination.items_per_page_options: [5,10,30]`, `pagination.default_items_per_page: 5`
  - City (admin): опции `[30,60,100]`, дефолт `30`
  - Pvz (admin): опции `[30,60,100]`, дефолт `30`
  - Глобальный максимум: `pagination.max_items_per_page: 100`
- Требования для admin‑ресурсов:
  - `#[ApiResource]` на Entity
  - Настройка пагинации в `GetCollection`
  - Корректные группы сериализации для admin‑контекста
  - При необходимости — `uriTemplate` под `/admin/{resource}`

## Инварианты и обязательные правила

- Конфиг‑менеджмент: только ENV + параметры + DI (`config/packages/*.yaml`, `config/services.yaml`, `ParameterBagInterface`); никаких хардкодов
- Вся бизнес‑логика, фильтрация, сортировка, пагинация — на бэкенде (API Platform)
- Frontend (Vue): только представление, UI‑состояние, загрузки, обработка ошибок; синхронизация пагинации через URL query (`page`, `itemsPerPage`)
- Корзина: источник истины — серверные `Cart/CartItem`; фронтенд не пересчитывает значения, а читает их из API
- Новые admin‑страницы — только через Vue Router (SPA), без Symfony‑роутов

## Логи и диагностика (Windows PowerShell)

- Основные файлы: `var/log/dev.log`, `var/log/doctrine_dev.log`, `var/log/request_dev.log`, `var/log/deprecation_dev.log`, `var/log/security_dev.log`
- Диагностика:
  - Последние ошибки: `Get-Content var/log/dev.log -Tail 20`
  - HTTP/роутинг: `Get-Content var/log/request_dev.log -Tail 10`
  - Doctrine: `Get-Content var/log/doctrine_dev.log -Tail 15`; `php bin/console doctrine:schema:validate`
  - Аутентификация: `Get-Content var/log/security_dev.log -Tail 10`
  - Deprecated: `Get-Content var/log/deprecation_dev.log -Tail 10`
  - Мониторинг: `Get-Content var/log/dev.log -Wait -Tail 5`

## Ключевые пути репозитория

- Backend: `src/{Controller,Entity,Repository,Service,Api,State,Event*,Validator,Exception}`
- Конфиги: `config/{packages, routes, services.yaml, bundles.php}`
- Admin (SPA): `assets/admin/{router,views,components,stores,services,...}`
- Catalog (FSD): `assets/catalog/src/{shared,features,widgets,entities,pages}`
- Сборка: `webpack.config.js`, `postcss.config.js`, `tailwind.config.js`, `tsconfig*.json`
- Артефакты: `public/build/`, `public/storybook-*`
- Документация: `docs/*`

## Критерии приёмки изменений (для задач ИИ)

- Соответствие инвариантам конфигурации и архитектуры (без хардкодов, DI, ENV)
- Пагинация/фильтры/сортировка делаются на бэкенде; во Vue только запросы и отображение
- Admin‑страницы регистрируются в Vue Router; URL‑синхронизация `page`/`itemsPerPage`
- Корзинные значения читаются из API; нет клиентских пересчётов
- Код собирается из корня командами npm; артефакты не коммитятся
- Отсутствуют логи/секреты в VCS; логи — в `var/log/*`

## Рантайм-артефакты

Снимки для ускорения навигации и анализа ИИ (генерируются локально, не для prod):
- `docs/runtime/container.json` — карта DI-контейнера
- `docs/runtime/routes.json` — маршруты
- `docs/runtime/composer-deps.json` — прямые зависимости Composer
- `docs/runtime/openapi.json` и `docs/runtime/openapi.yaml` — контракт API Platform (OpenAPI)
- `docs/db/schema.sql`, `docs/db/entities.txt` — SQL-дифф схемы БД и список сущностей Doctrine

Генерация (Windows PowerShell):
```
powershell -ExecutionPolicy Bypass -File docs/refresh.ps1
```

Примечание: Messenger не используется — снимок по шинам не генерируется.


