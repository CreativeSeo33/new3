## Система регистрации и аутентификации пользователей (Customer Auth)

Обновлено: 2025-10-06

### Назначение
Полное описание реализованной системы регистрации по email, входа/выхода, верификации email, восстановления пароля и управления JWT (access/refresh) в проекте Symfony 7 + API Platform + LexikJWT. Документ для разработчиков бэкенда/фронтенда.

### Архитектура и границы
- Разделение user/admin: пользовательские эндпоинты — под `^/api/customer` и страницы `/auth/*`, админ — без изменений.
- Stateless API, JWT в httpOnly cookie: `__Host-acc` (access) и `__Host-ref` (refresh).
- Refresh‑токены и одноразовые токены (verify/reset) — opaque, хранятся в БД в виде хешей (HMAC‑SHA256 + salt + pepper).
- Анти‑брутфорс: RateLimiter (регистрация/логин/refresh/запрос на сброс пароля) и анти‑тайминг.
- Немедленная инвалидция access по `tokenVersion` (claim `tv`), подписывается на этапе создания JWT; проверяется при декодировании.

---

### Сущности и миграции
- `App\Entity\User`
  - Поля: `email: unique`, `isVerified: bool`, `lastLoginAt: ?datetime_immutable`, `failedLoginAttempts: int`, `lockedUntil: ?datetime_immutable`, `tokenVersion: int`.
  - Роль по умолчанию: `ROLE_USER` (гарантируется в `getRoles()`).
- `App\Entity\UserRefreshToken`
  - Поля: `user`, `tokenHash`, `salt`, `expiresAt`, `rotatedAt`, `revoked: bool`, `uaHash`, `ipHash`, `createdAt`.
  - Назначение: управление refresh, ротация, отзыв, повторное использование → 409/401.
- `App\Entity\UserOneTimeToken`
  - Поля: `user`, `type` (`verify_email`|`password_reset`), `tokenHash`, `salt`, `expiresAt`, `used: bool`, `createdAt`.
  - Назначение: верификация email и сброс пароля.
- Миграция: `migrations/Version20251006130900.php` — добавляет поля к `users` и создаёт таблицы `user_refresh_token`, `user_one_time_token`.

---

### Конфигурация (ENV/parameters)
- Основные переменные окружения (см. `config/services.yaml`):
  - `JWT_ACCESS_TTL` (сек), `JWT_REFRESH_TTL` (сек)
  - `AUTH_MAX_FAILED`, `AUTH_LOCK_MINUTES`
  - `AUTH_FAILURE_DELAY_MS_MIN/MAX` — анти‑тайминг
  - `AUTH_VERIFY_TTL` (сек), `AUTH_PWD_RESET_TTL` (сек)
  - `APP_FRONTEND_BASE_URL` — базовый фронтенд URL для ссылок verify/reset
  - `APP_PEPPER` — общий pepper для хешей
  - `AUTH_DISPOSABLE_DOMAINS_PATH` — denylist disposable‑доменов
  - `MAILER_DSN`, `ADMIN_FROM_EMAIL`

---

### Security
- `config/packages/security.yaml`
  - Firewalls:
    - `api_customer_auth` → `^/api/customer/auth`, `stateless: true`, `security: false`
    - `api_customer` → `^/api/customer`, `stateless: true`, `provider: app_user_provider`, `jwt: ~`
    - `account` → `^/account`, `stateless: true`, `provider: app_user_provider`, `jwt: ~`
    - Существующий публичный `^/api` и админ — без изменений
  - Access control:
    - `^/api/customer/auth` → `PUBLIC_ACCESS`
    - `^/api/customer` → `IS_AUTHENTICATED_FULLY`
    - `^/account` → `IS_AUTHENTICATED_FULLY`
- Пароли: `password_hashers` = `auto` (приоритет argon2id)
- LexikJWT (`config/packages/lexik_jwt_authentication.yaml`):
  - `token_ttl: %app.auth.access_ttl%`
  - Экстракторы: cookie `__Host-acc` включен; заголовок Authorization включён
  - `remove_token_from_body_when_cookies_used: true`
  - `set_cookies.__Host-acc` настроен (access cookie)
- Приоритет cookie над Authorization: декоратор `App\Security\Jwt\CookieFirstChainExtractor` заменяет chain extractor и сначала проверяет cookie.

---

### JWT и Cookies
- Access: `__Host-acc`, httpOnly, Secure (prod), `SameSite=Lax`, TTL = `JWT_ACCESS_TTL`.
- Refresh: `__Host-ref`, httpOnly, Secure (prod), `SameSite=Strict`, TTL = `JWT_REFRESH_TTL`; устанавливается вручную через `RefreshTokenManager`.
- Ротация refresh: при `/api/customer/auth/refresh` старый токен помечается `rotatedAt`/`revoked`, новый выдаётся с новым `salt`.
- Отзыв всех refresh: `/api/customer/auth/revoke-all` + инкремент `User.tokenVersion`.

---

### Анти‑брутфорс / анти‑тайминг / CORS
- RateLimiter (`config/packages/framework.yaml`):
  - `auth_register` 20/мин (IP), `auth_login` 10/мин (IP), `auth_refresh` 5/мин (IP), `auth_pwd_request` 20/мин (IP)
- Anti‑Timing: `App\Service\Auth\AntiTimingService` — добавляет случайные задержки на неуспехах.
- Anti‑enumeration: унифицированные ответы на register/password request независимо от существования email.
- CORS (`config/packages/nelmio_cors.yaml`): профиль для `^/api/customer/auth` с `allow_credentials: true`, origin = `%app.frontend_base_url%`.

---

### Контроллеры (backend)
- `App\Controller\Api\Customer\AuthController`
  - `POST /api/customer/auth/register` — { email, password } → 201, письмо verify. Роль `ROLE_USER`. Лимитер: `auth_register`.
  - `POST /api/customer/auth/login` — { email, password } → 200, Set‑Cookie `__Host-acc`+`__Host-ref`, body `{ user }`. Анти‑тайминг, аудит, `lastLoginAt`.
  - `POST /api/customer/auth/refresh` — cookie‑based, ротация refresh, Set‑Cookie новых токенов.
  - `POST /api/customer/auth/logout` — 204, истекает `__Host-ref`; `__Host-acc` истекает через стандартные механизмы.
  - `POST /api/customer/auth/revoke-all` — 204, отзыв всех refresh + `tokenVersion++`.
  - `POST /api/customer/auth/password/request` — { email } → 202, письмо reset. Лимитер: `auth_pwd_request`.
  - `POST /api/customer/auth/password/confirm` — { token, password } → 200, смена пароля, отзыв всех refresh.
  - `POST /api/customer/auth/email/verify` — { token, email } → 200, `isVerified=true`.
- `App\Controller\Api\Customer\MeController`
  - `GET /api/customer/me` — под JWT, возвращает профиль `{ id, email, roles, isVerified, ... }`.
- Страницы (Symfony/Twig): `App\Controller\Site\AuthPageController`
  - `GET /auth/login` → `templates/security/customer_login.html.twig`
  - `GET /auth/register` → `templates/security/register.html.twig`
  - `GET /auth/password/request` → `templates/security/password_request.html.twig`
  - `GET /auth/password/reset` → `templates/security/password_reset.html.twig`
- Личный кабинет: `App\Controller\Account\AccountController` → `GET /account` (JWT‑защита).

---

### Сервисы (backend)
- `App\Service\Auth\RefreshTokenManager`
  - Создание/верификация/ротация/отзыв refresh, работа с cookie `__Host-ref`, хранение хеша (HMAC‑SHA256(secret=APP_PEPPER)+salt).
- `App\Service\Auth\OneTimeTokenManager`
  - Opaque verify/reset‑токены: генерация raw, хранится только хеш, TTL и одноразовость enforced в БД.
- `App\Service\Auth\DisposableEmailChecker` — denylist одноразовых доменов.
- `App\Service\Auth\MailerService` — письма verify/reset через Symfony Mailer; `From` из `%app.notification.from_email%`; ссылки строятся от `%app.frontend_base_url%`.
- `App\Security\Jwt\CookieFirstChainExtractor` — приоритет cookie над Authorization.
- `App\EventSubscriber\JwtTokenVersionSubscriber` — добавляет `tv` в payload и валидирует при декодировании.

---

### Фронтенд (Catalog, FSD)
- Модули: `assets/catalog/src/features/{auth-login,auth-register,auth-password}`; регистрация в `assets/catalog/src/app/registry.ts`.
- Страницы Twig уже подключают `data-module` и обеспечивают UX:
  - Регистрация: success → редирект `/auth/login`.
  - Логин: при наличии `verify_token` в URL после успешного входа — POST `/api/customer/auth/email/verify` и редирект `/account`.
- Навигация хедера обновлена на `/auth/login` и `/auth/register`.

---

### Почта (Mailer)
- Шаблоны: `templates/email/auth_verify.html.twig`, `templates/email/auth_password_reset.html.twig`.
- MAILER_DSN и `ADMIN_FROM_EMAIL` обязательны; подробный TODO — `docs/mailer_dsn_todo.md`.

---

### Логи/аудит
- Рекомендуется логировать события безопасности (логин/ошибки/lockout/refresh‑replay/ revoke) в security‑канал без секретов.

---

### Потоки (TL;DR)
1) Регистрация → 201, письмо verify (ссылка на `%APP_FRONTEND_BASE_URL%/auth/login?verify_token=...&email=...`).
2) Логин → 200 + cookies + `{ user }` → фронт обработает verify_token и вызовет `/email/verify`.
3) Доступ к `/api/customer/me` → 200 при валидном access.
4) Refresh → 200, ротация cookies; повторы старого refresh → 409/401 и отзыв.
5) Revoke‑all → 204, `tokenVersion++` (все access становятся невалидными).
6) Password request → 202, письмо reset; confirm → смена пароля и отзыв всех refresh.
7) Logout → 204, истечение refresh cookie.

---

### Известные ограничения и следующие шаги
- Проверка `isVerified` перед выдачей refresh ещё не принудительна (можно включить режим «без refresh до верификации» в `login`).
- Lockout per‑account: поля есть, но логика блокировки не активирована (инкремент/сброс/окно блокировки).
- Политика сложности пароля (длина/класс символов) — добавить валидатор (422) на бэке.
- Кэш `tokenVersion` — рекомендуется Redis с TTL = `access_ttl + 60s`.

---

### Приёмочные критерии
- Cookie‑first экстракция JWT работает; `__Host-acc` побеждает Authorization.
- Ротация refresh и отзыв всех сессий работают; повтор старого refresh → 409/401.
- Verify/reset — одноразовые токены, TTL, безопасные ответы (anti‑enumeration).
- Фронт формы работают без inline JS, через FSD модули.
- Admin SPA и публичные ресурсы API Platform не сломаны.

### Полезные пути
- Контроллеры: `src/Controller/Api/Customer/*`, `src/Controller/Site/AuthPageController.php`, `src/Controller/Account/AccountController.php`
- Сервисы: `src/Service/Auth/*`, `src/Security/Jwt/CookieFirstChainExtractor.php`, `src/EventSubscriber/JwtTokenVersionSubscriber.php`
- Конфиги: `config/packages/{security.yaml, lexik_jwt_authentication.yaml, nelmio_cors.yaml, framework.yaml}`, `config/services.yaml`
- Фронт: `assets/catalog/src/features/{auth-login,auth-register,auth-password}`, `assets/catalog/src/app/registry.ts`


