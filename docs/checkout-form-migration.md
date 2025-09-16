# Миграция checkout формы в FSD‑модуль

Кратко: вынесли inline‑скрипт из `templates/catalog/checkout/index.html.twig` в feature‑модуль `assets/catalog/src/features/checkout-form`.

Что сделано
- Добавлен модуль `checkout-form`:
  - `api/index.ts` — `submitCheckout(url, data)` через `@shared/api/http.post`
  - `ui/component.ts` — кеш формы в `localStorage`, сабмит, редирект
  - `index.ts` — `init(root, opts)`/`destroy()`
- Зарегистрирован модуль в реестре: `assets/catalog/src/app/registry.ts`
- В шаблоне:
  - На корневой контейнер добавлены `data-module="checkout-form"` и `data-submit-url` (Twig `path('checkout_submit')`)
  - Удалён inline `<script>`
- Обновлена карта страницы: `.cursor/rules/page_checkout_map.mdc`

DOM
- Корневой контейнер: `[data-testid="checkout-root"]`
- Форма: `#checkout-form`
- Кнопка: `#place-order`

Конфигурация
- CSRF/заголовки — централизованно в `@shared/api/http`

Сборка
- Запуск из корня: `npm run build:catalog` или `npm run dev:catalog`


