## Задача

Сгенерировать трассировку конкретного флоу в репозитории в формате parse‑friendly Markdown и сохранить в `docs/traces/<id>.md`.

## Формат файла трассировки

В начале — YAML‑шапка с унифицированными полями:

```yaml
---
trace_id: <kebab-id>
updated: YYYY-MM-DD
entry: <type>            # http, dom, http+dom, job, cli, event
request:                 # если есть HTTP‑часть
  method: <HTTP>
  url: </path>
controller:              # если есть backend‑контроллер
  class: <FQCN>
  file: <path>
  route: </path>
  action: <method>
scope:
  include:
    - templates/**
    - assets/**
    - src/Controller/**
    - src/**/Cart**
    - config/routes*.yaml
  exclude:
    - **/node_modules/**
    - **/dist/**
    - **/.next/**
    - **/vendor/**
    - **/public/build/**
flow:
  - <path>:<line> <symbol|note>
  - ...
---
```

Обязательные разделы в теле файла:
- `## http`: метод/URL/заголовки/тело/коды/ошибки (если применимо)
- `## security`: предикаты/идемпотентность/кэш/аутентификация
- `## side_effects`: cookie/база/транзакции/события
- `## repro`: шаги воспроизведения (1‑5 пунктов)
- `## links`: файлы и карты, связанные с флоу (page maps, services)
- `## summary`: 2‑3 строки сути флоу
- `## TODO`: открытые вопросы (если нет — «Нет»)

Требования к `flow`:
- Каждый пункт: `path:line → symbol` без скобок; кратко и точно
- Включать UI (templates), реестр, обработчик, API‑функцию, маршрут, контроллер, ключевые сервисы/вызовы

## Правила поиска (Windows PowerShell)

Выполнять не более 3 целевых rg‑поисков и не более 5 открытий файлов (≤120 строк каждый, один файл — один раз):
- `rg -n "Добавить в корзину|add-to-cart|data-add-to-cart" templates assets`
- `rg -n "(fetch|axios|post)\s*\(" assets`
- `rg -n "(#\\[Route\\(|@Route\\().*(cart|basket|add)" src`
- `rg -n "^\s*(path:|controller:).*(cart|basket)" config/routes*.yaml`

Стоп‑условия
- Нашёл точные места (кнопка/селектор, API функция, маршрут, контроллер) — прекращай широкие сканы

## Интеграция с Cursor Rules

- После создания трассировки добавь ссылки на неё:
  - `/.cursor/rules/page_product_map.mdc` → `links: [ ..., docs/traces/<id>.md ]`
  - `/.cursor/rules/page_cart_map.mdc` → `links: [ ..., docs/traces/<id>.md ]`
  - `/.cursor/rules/services.mdc` → раздел «Связанные трассировки» с ссылкой

## Конфигурация cookie корзины (для Cart флоу)

- DI параметризация: `config/services.yaml`
  - `cart.cookie.force_secure_in_prod: true`
  - `cart.cookie.use_host_prefix: true`
  - `cart.cookie.name: 'cart_id'` (в runtime → `__Host-cart_id`)
  - `cart.cookie.ttl_days: 180`
  - проброс аргументов в `App\Http\CartCookieFactory`

## Гайд по качеству

- Указывать `path:line` на каждую существенную точку
- Исключать шум и расплывчатые формулировки
- Маски `exclude` — только с `**/` префиксом
- События оформлять как точный вызов (например, `window.dispatchEvent(new CustomEvent('cart:updated', { detail }))`)


