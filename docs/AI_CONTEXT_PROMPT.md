## Задача

Сгенерируй файл `docs/AI_CONTEXT.md` — якорный контекст для будущих задач ИИ по этому репозиторию. Документ должен быть кратким, техническим, точным, на русском. Он фиксирует архитектуру, правила и инварианты проекта, чтобы любые следующие задачи выполнялись консистентно и без регрессий.

## Формат и стиль

- **язык**: русский, тон — технический, без воды
- **заголовки**: используй только `##` и `###`
- **списки**: маркеры `- `; короткие, насыщенные пункты
- **код**: только где уместно; файлы/директории/классы/функции — в обратных кавычках
- **объём**: 1–3 страницы; без лишних общих рассуждений
- **безопасность**: не публикуй секреты; указывай только имена переменных окружения

## Выходной файл

- Путь: `docs/AI_CONTEXT.md`
- Если файл существует — перезаписать полностью

## Обязательные разделы (с содержанием)

### Обзор проекта
- **назначение**: Symfony 7 backend + Vue 3 frontend, e‑commerce сущности, API Platform
- **ключевые домены**: каталог, корзина/заказ, доставка, админка

### Технологический стек и версии
- **PHP**: >= 8.2
- **Symfony**: 7.3.* (Flex, автоконфигурация, DI)
- **API Platform**: ^4.1; форматы: jsonld, json, html; merge-patch json
- **Doctrine ORM/DBAL**: ORM ^3.5, DBAL ^3
- **Auth**: LexikJWTAuthenticationBundle ^3.1 (пакет установлен), основная авторизация — `security.yaml`
- **Frontend**: Vue ^3.5.18, Vue Router ^4.5.1, Pinia ^3.0.3
- **Сборка**: Webpack Encore (^5) — продовая сборка; Vite ^5 — для Storybook/локальных задач
- **CSS**: TailwindCSS ^3.4.17 (PostCSS)
- **Storybook**: 8.6 (html-vite, vue3-vite)
- **TypeScript**: ^5.9.2 (strict=true)

### Сборка и команды
- npm (root): `dev`, `watch`, `build`, `dev:admin`, `build:admin`, `dev:catalog`, `build:catalog`
- Storybook: `storybook:*`, `build-storybook:*` (вывод в `public/storybook-*`)
- Бандлы Encore: точки входа `admin` (`assets/admin/admin.ts`), `catalog` (`assets/catalog/catalog.ts`)
- ВАЖНО: запуск сборки из корня: `npm run build` [[требование]]

### Архитектура фронтенда
- **Admin SPA**: Vue 3, history base `'/admin'` (`createWebHistory('/admin')`), роутинг только через Vue Router (не через Symfony) [[требование]]
- **Catalog (FSD)**: в `assets/catalog/src/` (shared, features, widgets, entities, pages)
- **Алиасы TS/Webpack**: `@`→`assets/catalog/src`, `@shared`, `@features`, `@entities`, `@widgets`, `@pages`, `@admin`
- **Tailwind**: `darkMode: 'class'`, контент сканируется по `templates/**/*.html.twig`, `assets/**/**.{js,vue,ts}`; расширенная тема, safelist
- **Типовой паттерн модулей (FSD)**: разнос API и UI; шаблоны в `docs/templates/` и генератор `docs/generate-module.js`
- **Правила**:
  - UI занимается только представлением; бизнес‑логика/фильтры/пагинация — на бэкенде (API Platform)
  - Для пагинации в Vue: синхронизировать `page` и `itemsPerPage` в URL query; читать из URL при загрузке

### Архитектура бэкенда
- **Bundles**: Framework, WebpackEncore, Twig, Security, Doctrine, Migrations, NelmioCors, ApiPlatform, Monolog, Debug/WebProfiler, LexikJWT, LiipImagine, UX (Stimulus, TwigComponent, LiveComponent, Turbo)
- **Routing**: контроллеры по атрибутам (`config/routes.yaml`), `api_login: /api/login`
- **Security**: `^/api` — публичный (security: false) для API Platform; `^/admin` — `ROLE_ADMIN`; form_login (`/login`), logout
- **CORS**: `allow_origin: %env(CORS_ALLOW_ORIGIN)%`, методы GET/OPTIONS/POST/PUT/PATCH/DELETE
- **Конфигурация**: жёсткие значения запрещены; использовать параметры/ENV, инъекцию через DI
- **Сервисные параметры (факты)**: конфигурация доставки, cookie корзины (имя `cart_id`, ttl 180 дней, SameSite=Lax, домен настраиваемый), registry провайдеров доставки, idempotency сервисы

### API Platform и пагинация
- `api_platform.yaml` (defaults):
  - `stateless: true`
  - `pagination_items_per_page: %pagination.default_items_per_page%`
  - `pagination_maximum_items_per_page: %pagination.max_items_per_page%`
  - `pagination_client_items_per_page: true`, `pagination_client_page: true`
- `config/packages/pagination.yaml`:
  - общие: `pagination.items_per_page_options: [5,10,30]`, `pagination.default_items_per_page: 5`
  - City (админ): опции `[30,60,100]`, дефолт `30`
  - Pvz (админ): опции `[30,60,100]`, дефолт `30`
  - максимум глобально: `100`
- Требования для admin‑ресурсов:
  - у Entity присутствует `#[ApiResource]`
  - в `GetCollection` настроена пагинация и лимиты
  - `uriTemplate` под `/admin/{resource}` при необходимости
  - группы сериализации для admin‑контекста

### Инварианты и критичные правила
- Конфиги через ENV/parameters/DI; никаких хардкодов URL/путей/констант бизнеса
- Вся пагинация, фильтрация, сортировка — строго на бэкенде (API Platform)
- Во Vue — только отображение, стейт UI, загрузки/ошибки; никакой бизнес‑логики
- Корзина: источник истины — серверная `Cart/CartItem`; фронт не пересчитывает, а читает значения из API [[требование]]
- Новые admin‑страницы — только через Vue Router, не через Symfony маршруты [[требование]]

### Логи и диагностика (Windows PowerShell)
- Основные файлы: `var/log/dev.log`, `var/log/doctrine_dev.log`, `var/log/request_dev.log`, `var/log/deprecation_dev.log`, `var/log/security_dev.log`
- Быстрые команды:
  - Последние ошибки: `Get-Content var/log/dev.log -Tail 20`
  - HTTP/роутинг: `Get-Content var/log/request_dev.log -Tail 10`
  - Doctrine: `Get-Content var/log/doctrine_dev.log -Tail 15`; `php bin/console doctrine:schema:validate`
  - Аутентификация: `Get-Content var/log/security_dev.log -Tail 10`
  - Deprecated: `Get-Content var/log/deprecation_dev.log -Tail 10`
  - Мониторинг: `Get-Content var/log/dev.log -Wait -Tail 5`

### Структура репозитория (ключевые пути)
- Backend: `src/` (Controller, Entity, Repository, Service, Api, State, Event*, Validator, Exception)
- Конфиги: `config/` (`packages/`, `routes/`, `services.yaml`, `bundles.php`)
- Frontend Admin: `assets/admin/` (router, views, components, stores, services)
- Frontend Catalog (FSD): `assets/catalog/src/` (shared, features, widgets, entities, pages)
- Сборка: `webpack.config.js`, `postcss.config.js`, `tailwind.config.js`, `tsconfig*.json`
- Публичные ассеты: `public/build/`, `public/storybook-*`
- Документация: `docs/` (архитектура, генератор модулей)

### Полезные соглашения и паттерны
- Vue 3 `<script setup lang="ts">`, Composition API, строгие типы
- Минимум кастомного CSS, упор на Tailwind utility‑классы
- Компоненты маленькие, одноназначные; общая логика — в composables
- Для новых модулей следовать FSD и шаблонам API/UI; чистка ресурсов в `destroy()`

## Источники истины (факты из репозитория; обнови при генерации)

- `composer.json`: Symfony 7.3.*, API Platform ^4.1, Doctrine ORM ^3.5, DBAL ^3, LexikJWT ^3.1, LiipImagine 2.12.3, Monolog 3, UX‑пакеты
- `package.json`: Vue ^3.5.18, Vue Router ^4.5.1, Pinia ^3.0.3, Tailwind ^3.4.17, Vite ^5.4.10, Encore ^5, TS ^5.9.2
- `webpack.config.js`: точки входа `admin`, `catalog`; алиасы `@`, `@shared`, `@features`, `@entities`, `@widgets`, `@pages`, `@admin`; Vue loader для admin; PostCSS включён
- `config/packages/api_platform.yaml`: включены клиентские параметры пагинации; стейтлес; форматы
- `config/packages/pagination.yaml`: опции и лимиты пагинации (общие/City/Pvz)
- `config/packages/security.yaml`: `^/api` — public; `^/admin` — `ROLE_ADMIN`; `form_login`/`logout`
- `config/packages/nelmio_cors.yaml`: `allow_origin` из ENV `CORS_ALLOW_ORIGIN`
- `assets/admin/router/index.ts`: `createWebHistory('/admin')`
- `tailwind.config.js`: `darkMode: 'class'`, расширенная тема, safelist
- `tsconfig.json`: `moduleResolution: bundler`, алиасы, `strict: true`

## Критерии приёмки результата

- Все перечисленные разделы присутствуют и заполнены фактами
- Указаны точные версии и ключевые настройки из файлов конфигурации
- Правила конфигурации/пагинации/архитектуры сформулированы как инварианты
- Документ укладывается в 1–3 страницы, легко читается по заголовкам и спискам
- Нет утечки секретов; используются названия ENV/параметров, а не значения

## Выполнить сейчас

1) Прочитать актуальные файлы (`composer.json`, `package.json`, `webpack.config.js`, `config/packages/*.yaml`, `assets/admin/router/index.ts`, `tailwind.config.js`, `tsconfig*.json`)
2) Сформировать `docs/AI_CONTEXT.md` по структуре выше
3) Перекрестно проверить фактологию с конфигами; при расхождении — брать данные из кода
4) Сохранить файл и завершить


