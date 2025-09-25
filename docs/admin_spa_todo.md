# Admin SPA — критические TODO для доведения до Production

> Этот файл фиксирует технические пробелы в текущей реализации Vue SPA для админки и даёт краткие задачи для устранения. Он дополняет правила в `.cursor/rules/` и не вносит изменения в код напрямую.

## 1) Аутентификация и доступ
- Добавить Pinia‑store `auth` с методами `login/logout`, хранением токена и профиля, геттерами `isAuthenticated`, `hasRole(role)`.
- Включить роут‑гварды:
  - `meta.requiresAuth: true` защищает админ‑маршруты.
  - `meta.roles?: string[]` проверяет доступ по ролям.
- Страница логина `/admin/login`: форма, вызов `POST /api/login` (возврат `{ token }`), сохранение токена и редирект на `dashboard`.
- HttpClient: на 401 триггерить сценарий разлогина (reset токена/стора) и редирект на `/admin/login` без залипания лоадера.

## 2) Инициализация приложения (bootstrap)
- Подключить Pinia в `admin.ts`: `createApp(App).use(pinia).use(router) ...`.
- В `router/index.ts` — единый `beforeEach` для проверки `requiresAuth`/`roles` и безопасной обработкой 401.

## 3) Конфигурация и ENV
- Источник baseUrl API: `window.APP_CONFIG.api.baseUrl` → fallback `VITE_API_URL` → `/api`.
- Пагинация: использовать `window.APP_CONFIG.pagination` как первичный источник (уже внедрено в Twig), API‑fallback — как сейчас в `services/config.ts`.

## 4) Ошибки и UX
- Нормализация 400/422: прокидывать `violations` в формы (текущий HttpClient нормализует текст; нужно расширить паттерн для violation mapping в формах репозиториев/компосаблов).
- Глобальный лоадер: убедиться, что при ошибках запросов всегда вызывается `stopGlobalLoading()` (учтено в HttpClient; оставить чек‑пойнт в тестах).

## 5) Репозитории и кэш
- Единообразие кэша GET: использовать встроенный TTL (60s), для справочников — `adminCache` (24h) с `invalidatePersistentCache()` в конкретных репозиториях.
- На все мутации (create/update/patch/delete/bulkDelete) — очистка кэшей (встроено), проверить переопределения.

## 6) Маяки и правила Cursor
- Проставить маяки в первых строках файлов:
  - `assets/admin/admin.ts`: `// ai:bootstrap area=admin uses=router,store`
  - `assets/admin/router/index.ts`: `// ai:router area=admin uses=auth,guards`
  - `assets/admin/services/http.ts`: `// ai:http-client area=admin exports=HttpClient,httpClient`
  - `assets/admin/stores/auth.ts`: `// ai:store area=admin name=auth exports=useAuthStore`
  - `assets/admin/repositories/BaseRepository.ts`: `// ai:repository-base area=admin`
- Применять новые правила: `@admin_js_architecture.mdc`, `@admin_auth_policy.mdc`, обновлённую `@agent_performance_policy.mdc`.

## 7) Тесты/качество (минимум)
- Unit: Vitest + Vue Test Utils для `auth` стора и роут‑гвардов (smoke‑тесты).
- API e2e (PHPUnit/ApiPlatform): тест логина и защищённых эндпоинтов (401/403/200).
- Линты/форматтеры (ESLint/Prettier) — убедиться, что конфиги согласованы с TS и Vue 3.

## 8) Безопасность
- Пересмотреть хранение токена: при возможности перейти на HttpOnly cookie; если остаётся `localStorage`, убедиться в строгом CSP и отсутствии inline JS.
- Настроить CORS для домена админки, отключить CSRF для JWT‑эндпоинтов.

---

### Быстрый чек‑лист выполнения
- [ ] Pinia подключён; есть `auth` стор с API
- [ ] Роут‑гвард `requiresAuth`/`roles` работает
- [ ] Страница `/admin/login` доступна и успешный логин ведёт на dashboard
- [ ] HttpClient на 401 корректно разлогинивает и редиректит
- [ ] baseUrl API определяется из `APP_CONFIG`/ENV
- [ ] Пагинация берётся из `APP_CONFIG`, fallback на API
- [ ] Маяки добавлены в ключевые файлы админки
- [ ] Базовые тесты/линты проходят
