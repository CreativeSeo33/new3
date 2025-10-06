### Промпт для внедрения регистрации по email и аутентификации (JWT) в текущий проект

- Цель: добавить безопасную регистрацию пользователя по email, вход/выход, верификацию email, восстановление пароля; базироваться на Symfony 7/API Platform/LexikJWT уже присутствующих; не ломать существующие фичи и конфиги.
- Приоритет: безопасность (anti‑bruteforce, anti‑enumeration, JWT best practices, httpOnly cookie, минимизация утечек).
- Учитывать будущие способы логина (телефон, соцсети) — дизайн API/моделей расширяемый.
- Соблюдать правила: `@hardcoderules.mdc`, `@projectrules.mdc`, `@AI_CONTEXT.md`, `@stimulus_policy.mdc`, `@catalog_js_architecture.mdc`, `@css_browser_compatibility_policy.mdc`, `@admin_auth_policy.mdc`. Не хардкодить; только через ENV/parameters/DI.
- Не ломать Admin SPA (JWT уже есть), Catalog (Twig+Stimulus/FSD), API Platform `stateless: true`.

### Контекст (существующее)
- Symfony 7.3, API Platform ^4.1 (`stateless: true`, `^/api` сейчас публично), LexikJWT установлен (`config/packages/lexik_jwt_authentication.yaml`).
- Entity `App\Entity\User` существует (см. `docs/doctrine_entities.mdc`).
- Admin SPA аутентифицируется через JWT (`/api/login`), фронт каталога — Twig/Stimulus/FSD.
- CORS из `CORS_ALLOW_ORIGIN`. Нельзя ломать текущие маршруты API.

### Требования к реализации (Backend)

1) Модель/миграции (минимально и расширяемо)
- В `App\Entity\User`:
  - Гарантировать уникальность `email` (LOWER, нормализация), поля:
    - `isVerified: bool` (default=false, not null).
    - `password: string` (argon2id предпочтительно).
    - Аудит: `lastLoginAt`, `failedLoginAttempts: int`, `lockedUntil: \DateTimeImmutable|null`.
  - Не менять смысл существующих полей/ролей. Default роль: `ROLE_USER`.
- Миграции Doctrine — отдельный коммит; схема не ломает совместимость.

2) Маршруты/эндпойнты (контроллеры, вне API Platform ресурсов)
- Пространство путей: `/api/customer/auth/*` (публичные операции), `/api/customer/*` (требуют JWT). Прежний `/api/login` для админки не трогаем.
- Разделить контроллеры по ответственности (SRP):
  - `App\Controller\Api\Auth\RegistrationController`
  - `App\Controller\Api\Auth\AuthenticationController`
  - `App\Controller\Api\Auth\PasswordController`
  - (опционально) `App\Controller\Api\Auth\TokenController` для refresh/revoke
  - POST `/api/customer/auth/register` — body `{ email, password }`
    - Валидации: email формат, пароль (длина/сложность), нормализация email в lower.
    - Создать пользователя inactive (`isVerified=false`); отправить письмо с верификацией; 201 без утечки, ответ общий «Письмо отправлено, проверьте почту».
    - Anti‑enumeration: одинаковое сообщение для существующего email.
    - RateLimiter `auth_register`: ключ ТОЛЬКО по IP (например 20/мин/IP). Не использовать email в ключе для публичных эндпойнтов; после порога — включать CAPTCHA/пазл.
  - POST `/api/customer/auth/login` — body `{ email, password }`
    - Проверка lockout/credential; на успех — выдать Access JWT (ttl=15 мин) + Refresh (ttl=30 дней) через httpOnly cookies (`__Host-acc`, `__Host-ref`), SameSite=Lax/Strict, Secure в prod; альтернатива — Authorization Bearer для совместимости.
    - На 401 — не раскрывать причину (общая «Неверные учётные данные»); инкремент 실패, временный lockout (например 10 попыток → 15 минут).
    - RateLimiter `auth_login` (например 10/мин/IP).
  - POST `/api/customer/auth/refresh` — cookie‑based, ротация refresh (скользящее окно). На успех — новые cookies, старый refresh инвалидировать. RateLimiter `auth_refresh`.
  - POST `/api/customer/auth/logout` — очистить cookies (Set‑Cookie с прошедшей датой), инвалидировать refresh (если хранится в БД).
  - POST `/api/customer/auth/password/request` — body `{ email }` — всегда 202; отправить письмо со ссылкой; RateLimiter `auth_pwd_request`: ключ ТОЛЬКО по IP (например 20/мин/IP) + CAPTCHA после порога.
  - POST `/api/customer/auth/password/confirm` — body `{ token, password }` — валидации, одноразовый токен, TTL (например 30 мин), поменять пароль (argon2id), инвалидировать все refresh.
  - POST `/api/customer/auth/email/verify` — body `{ token }` — one‑time, TTL (например 24ч), устанавливает `isVerified=true`. Не использовать GET, чтобы исключить утечки токена в логи/историю/Referrer.
  - POST `/api/customer/auth/revoke-all` — отзыв всех активных refresh‑токенов пользователя (logout со всех устройств).
- Инфо‑эндпойнты под защитой JWT:
  - GET `/api/customer/me` — возвращает профиль (`id, email, roles, isVerified, createdAt`).
- Токены:
  - Email verify/reset — предпочтительно opaque‑токены: генерировать случайный токен, хранить ХЕШ (HMAC/SHA256) с TTL и флагом `used` в БД; одноразовость гарантируется БД. Если используется signed JWT — отдельный ключ и хранение `jti` для немедленной ревокации.
- Ошибки:
  - 400/422 — формат и валидация; 401 — неверный логин; 429 — лимитер; 409 — конфликт токенов/ресурса; 410 — просрочен verify/reset.

3) Security (firewalls/access control) — не ломать публичный API Platform
- В `config/packages/security.yaml`:
  - Добавить специализированные firewalls выше общего `^/api`:
    - `api_customer_auth`: `pattern: ^/api/customer/auth` → `stateless: true`, anonymous: true.
    - `api_customer`: `pattern: ^/api/customer` → `stateless: true`, `jwt` (Lexik), access control: `IS_AUTHENTICATED_FULLY`.
  - Общий публичный firewall: `^/api` (public/security: false) — должен идти после двух выше; без негативных масок/сложных regex.
  - Подключить извлекатели токенов LexikJWT с детерминированным приоритетом: `cookie` (name `__Host-acc`) > `authorization_header`. При одновременном наличии побеждает cookie; поведение покрыть тестами.
- Включить RateLimiter (component) конфиги:
  - `framework.rate_limiter`: `auth_register`, `auth_login`, `auth_refresh`, `auth_pwd_request`, ключи IP+email где релевантно.

4) Пароли и хеширование
- `security.password_hashers`: для `User` — `auto` с приоритетом `argon2id`; миграция legacy хешей через `migrate_from` (если есть).
- Политика паролей (усиленная):
  - Минимум 10 символов И включая: 1 заглавную, 1 строчную, 1 цифру, 1 спецсимвол; ИЛИ passphrase ≥ 14 символов.
  - Блокировка распространённых паролей (blacklist), опционально оценка `zxcvbn`.
  - Серверная валидация + единые сообщения об ошибке (без утечек конкретики).

5) Почта
- Использовать Symfony Mailer (`MAILER_DSN`), шаблоны писем Twig.
- Ссылка верификации/сброса — абсолютный URL; не раскрывать наличие аккаунта в ответах API.

6) Refresh‑tokens (надёжность)
- Варианты:
  - Встроенная реализация: хранить HMAC‑SHA256 хеш refresh‑токена с пер‑токенным `salt` и серверным `pepper` (`%env(APP_PEPPER)%`) в таблице `user_refresh_token` (`userId`, `tokenHash`, `salt`, `expiresAt`, `rotatedAt`, `revoked:boolean`, `uaHash`, `ipHash`, `createdAt`). Не хранить raw токен.
  - Или `gesdinet/jwt-refresh-token-bundle` (если приемлемо) — конфиг через ENV.
- Ротация обязательна; на повтор прежнего refresh — 401 и отзыв всей сессии.
- Поддержать отзыв всех токенов пользователя (см. endpoint `revoke-all`).
 - Индексировать `expiresAt`, настроить периодическую очистку (retention), хранить `ipHash/uaHash` вместо raw значений.

7) Логи/аудит
- Логировать безопасность в отдельный канал (security): попытки логина, lockout, password reset, revocation/replay (без секретов).
- Не логировать raw tokens/passwords; email/IP хранить в маскированном/хешированном виде; ограничить доступ к логам и задать retention.

8) Конфигурация (ENV/parameters)
- Добавить параметры:
  - `JWT_ACCESS_TTL=900` (сек), `JWT_REFRESH_TTL=2592000`, `AUTH_MAX_FAILED=10`, `AUTH_LOCK_MINUTES=15`, `APP_FRONTEND_BASE_URL`, `MAILER_DSN`, `APP_PEPPER`, `AUTH_FAILURE_DELAY_MS_MIN=120`, `AUTH_FAILURE_DELAY_MS_MAX=280`, `AUTH_DISPOSABLE_DOMAINS_PATH` (путь к denylist).
  - `config/services.yaml`:
    - `app.auth.access_ttl: '%env(int:JWT_ACCESS_TTL)%'`
    - `app.auth.refresh_ttl: '%env(int:JWT_REFRESH_TTL)%'`
    - `app.auth.max_failed: '%env(int:AUTH_MAX_FAILED)%'`
    - `app.auth.lock_minutes: '%env(int:AUTH_LOCK_MINUTES)%'`
    - Не хардкодить URL/TTL.

9) Anti‑timing / anti‑enumeration усиление
- Сгладить время ответа для неуспешных аутентификаций: добавлять случайную задержку `AUTH_FAILURE_DELAY_MS_MIN..MAX`.
- Сравнения секретов и одноразовых токенов выполнять константным временем.
- Единые ответы при register/password request, вне зависимости от наличия пользователя.
 - RateLimiter: для публичных эндпойнтов (register/password request) — ключ по IP. Для логина — ключ по IP. Если требуется компонент на email (внутренние лимиты) — использовать HMAC(email) и не отражать это вовне; возвращать одинаковые заголовки (`Retry-After`).

10) Валидация email‑доменов
- Добавить проверку на disposable‑домены (denylist) через список из `AUTH_DISPOSABLE_DOMAINS_PATH` (обновляемый), с возможностью переопределить через сервис.

11) Немедленная инвалидция access‑токенов (без нагрузки на SQL)
- Добавить в `User` поле `tokenVersion:int` (или `passwordChangedAt: \DateTimeImmutable`).
- Включать это значение в payload JWT (например, claim `tv` или `pwd_at`).
- Кэшировать актуальную версию в Redis под ключом `user_token_version:{userId}` с TTL = `access_ttl + 60s`.
- На каждом запросе сравнивать `tv` из токена со значением из Redis. При отсутствии ключа — один fallback в SQL с последующим кэшированием. При смене пароля/`revoke-all` — инкрементировать значение в SQL и `DEL user_token_version:{userId}`.

### Требования к реализации (Frontend Catalog, Twig/Stimulus/FSD)

- Страницы/формы:
  - Регистрация: `templates/security/register.html.twig` (или инклюд) + модуль FSD `assets/catalog/src/features/auth-register`.
  - Логин: `templates/security/login.html.twig` + модуль `features/auth-login`.
  - Восстановление пароля: `templates/security/password_request.html.twig`, `password_reset.html.twig` + `features/auth-password`.
- Политика Stimulus‑first:
  - Без inline `<script>`. Все взаимодействия — через FSD модули или Stimulus контроллеры; HTTP — только через `@shared/api/http`.
- Куки:
  - Клиент НЕ хранит токены в localStorage (по умолчанию httpOnly cookie). Для SSR частей (Twig) этого достаточно.
- UX/безопасность:
  - Общие тексты ответов (anti‑enumeration).
  - Капча/пазл опционально за feature‑flag при аномалиях.
- Расширяемость:
  - Подготовить UI к альтернативным логинам (телефон) — модульная структура, табы.

### Совместимость с Admin SPA

- Не менять существующий `/api/login` для админки. Никаких регрессий в `assets/admin/services/http.ts` и гвардах. 401/403 поведение не трогаем.
- Разделение пространств: пользовательский `/api/auth/*` и админский не пересекаются.

### Безопасность (обязательные меры)

- Anti‑brute‑force: RateLimiter на каждую критическую операцию + lockout per‑account.
- Anti‑enumeration: одинаковые ответы на register/password request, тайминги сглажены.
- Cookies: `__Host-` префикс, `Secure` (prod), `HttpOnly`, `SameSite=Lax` (access)/`Strict` (refresh).
- CORS: выделить отдельный профиль для `/api/customer/auth/*` (nelmio_cors) с `allow_origin: [APP_FRONTEND_BASE_URL]`, `allow_credentials: true`, ограниченными методами/заголовками; никогда не использовать `*` вместе с credentials. Для остальных путей оставить базовую политику без credentials.
- Верификация email обязательна перед выдачей refresh (опционально — allow login без refresh до верификации).
- Инвалидация всех refresh при смене пароля.
- Валидация входных данных, нормализация email (lowercase, trim), безопасные сообщения об ошибках.
- Unit/functional тесты на lockout, refresh rotation, verify/reset потоки.

### Эндпойнты: контракты (TL;DR)

```http
POST /api/customer/auth/register
Content-Type: application/json
{ "email": "user@example.com", "password": "P@ssw0rd!" }
→ 201 { "status": "ok" }  ; 429/422 on limits/validation

POST /api/customer/auth/login
Content-Type: application/json
{ "email": "user@example.com", "password": "P@ssw0rd!" }
→ 200 Set-Cookie: __Host-acc=...; __Host-ref=... ; body { "user": { ... } }

POST /api/customer/auth/refresh
→ 200 Set-Cookie rotated ; body { "status": "ok" } ; 401/409 on replay

POST /api/customer/auth/logout
→ 204 Set-Cookie expired

POST /api/customer/auth/password/request
{ "email": "user@example.com" }
→ 202 { "status": "ok" }

POST /api/customer/auth/password/confirm
{ "token": "<opaque>", "password": "NewP@ssw0rd!" }
→ 200 { "status": "ok" }

POST /api/customer/auth/email/verify
Content-Type: application/json
{ "token": "<opaque>" }
→ 200 { "status": "ok" }

POST /api/customer/auth/revoke-all
→ 204  ; отзывает все refresh‑токены пользователя

GET /api/customer/me
Authorization: Bearer <optional if no cookie>
→ 200 { "id", "email", "roles", "isVerified", ... }
```

### Тест‑план/приёмка

- Регистрация:
  - Повтор email → всегда одинаковый ответ; письмо отправляется только 1 раз/TTL окна.
  - Email verify: токен одноразовый, просрочка → 410.
- Логин:
  - Неверные — 401 и счётчик; после `AUTH_MAX_FAILED` — lockout.
  - Успех — устанавливаются 2 cookie; `me` доступен, `refresh` вращается.
- Сброс пароля:
  - Request всегда 202; confirm меняет пароль, инвалидирует refresh, позволяет вход.
- Безопасность:
  - RateLimiter срабатывает; нет утечки конкретики; cookies с флагами; CORS не «открыт».
- Не регрессирует Admin SPA и публичные API Platform ресурсы.

### Внесение конфигурации

- `.env` (пример):
  - `JWT_ACCESS_TTL=900`
  - `JWT_REFRESH_TTL=2592000`
  - `AUTH_MAX_FAILED=10`
  - `AUTH_LOCK_MINUTES=15`
  - `APP_FRONTEND_BASE_URL=https://example.com`
  - `MAILER_DSN=smtp://localhost:1025`
- `config/services.yaml` — параметры `app.auth.*` (как выше).
- `security.yaml` — новые firewalls (`api_auth`, `api_user`) ДО публичного `^/api/(?!auth|user)`.

### Frontend (Catalog) — минимальные модули (FSD)

- `assets/catalog/src/features/auth-register/{api/index.ts, ui/component.ts, index.ts}`
- `assets/catalog/src/features/auth-login/{api/index.ts, ui/component.ts, index.ts}`
- `assets/catalog/src/features/auth-password/{api/index.ts, ui/component.ts, index.ts}`
- Регистрация в `assets/catalog/src/app/registry.ts`:
  - `'auth-register'`, `'auth-login'`, `'auth-password'`
- Разметка Twig — только `data-module="auth-register|auth-login|auth-password"`, без inline JS.

### Не делать

- Не менять существующие Admin SPA эндпойнты/гварды.
- Не ослаблять глобально CORS/безопасность cookies.
- Не хранить токены в localStorage.
- Не хардкодить URL/TTL/пороги — только через конфиг.

### Коммиты

- 1) backend: сущности/миграции/контроллеры/security/rate‑limiters → тесты
- 2) backend: почта/шаблоны писем
- 3) frontend: FSD модули и Twig
- 4) конфиги ENV/parameters
- Без изменений в бизнес‑логике каталога/корзины.

### Примечание по расширяемости (телефон/соцсети)

- Проектировать `AuthController` так, чтобы легко добавить:
  - `/api/auth/login/phone`, `/api/auth/login/oauth/{provider}`
  - Общая инфраструктура токенов/refresh/лимитеров используется повторно.

— Этого достаточно, чтобы реализовать безопасную регистрацию и аутентификацию по email в текущем стеке без регрессий и с будущим расширением на иные методы логина.

Коротко: добавляем контроллеры `/api/auth/*`, `api_user` firewall с JWT, httpOnly cookies для токенов, rate‑limit, verify/reset потоки, FSD модули для форм; не трогаем Admin SPA/публичные API.