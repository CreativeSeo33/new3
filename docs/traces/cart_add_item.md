---
trace_id: cart_add_item
updated: 2025-09-17
entry: http+dom
request:
  method: POST
  url: /api/cart/items
controller:
  class: App\Controller\Api\CartApiController
  file: src/Controller/Api/CartApiController.php
  route: /api/cart/items
  action: addItem
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
  - templates/catalog/product/show.html.twig:291 data-module=add-to-cart (button)
  - templates/catalog/product/show.html.twig:305 text=«Добавить в корзину»
  - templates/catalog/category/_grid.html.twig:34 .js-add-to-cart (button)
  - assets/catalog/src/app/registry.ts:12 registry add-to-cart
  - assets/catalog/src/features/add-to-cart/ui/button.ts:51 init
  - assets/catalog/src/features/add-to-cart/ui/button.ts:89 handleClick
  - assets/catalog/src/features/add-to-cart/ui/button.ts:131 addToCart call
  - assets/catalog/src/features/add-to-cart/ui/button.ts:137 dispatch CustomEvent cart:updated
  - assets/catalog/src/features/add-to-cart/api/index.ts:94 addToCart
  - assets/catalog/src/features/add-to-cart/api/index.ts:114 post /api/cart/items
  - src/Controller/Api/CartApiController.php:110 addItem
  - src/Controller/Api/CartApiController.php:135 guard.assertPrecondition
  - src/Controller/Api/CartApiController.php:144 idem.begin
  - src/Controller/Api/CartApiController.php:214 manager.addItemWithChanges
  - src/Controller/Api/CartApiController.php:236 setStatusCode 201; cartResponse.withCart
---

## http

- Headers (опц.): Prefer (cart.full|cart.delta|cart.summary), Idempotency-Key, If-Match
- Body (JSON): { productId: number, qty: number, optionAssignmentIds?: number[] }
- Success: 201 Created (body зависит от Prefer)
- Errors: 422 invalid input; 412/428 preconditions; 409 insufficient_stock/conflict/in_flight

## security

- Preconditions (ETag/If-Match): src/Controller/Api/CartApiController.php:135 guard.assertPrecondition → 412/428
- Idempotency-Key: src/Controller/Api/CartApiController.php:144 idem.begin; 147 regex validation; 171 replay; 184 conflict 409; 199 in_flight 409; 239 finish
- Cache headers: src/Http/CartResponse.php:70 setResponseHeaders (ETag, Cache-Control=no-store, Cart-Version, totals)
- Auth model: гостевой или привязка к пользователю (userId из AppUser), доступ публичный
- Cart cookie DI override: config/services.yaml:32 cart.cookie.force_secure_in_prod=true; 33 cart.cookie.use_host_prefix=true; 34 name='cart_id'; 35 ttl_days=180; 36 domain=null; 37 same_site='lax'
- CartCookieFactory args: config/services.yaml:167-175 → $forceSecureInProd, $useHostPrefix, $cookieName, $ttlDays, $domain, $sameSite

## side_effects

- Cookie корзины (установка/продление):
  - src/Service/CartContext.php:68 response.headers.setCookie (новый токен)
  - src/Service/CartContext.php:81 response.headers.setCookie (миграция legacy)
  - src/Service/CartContext.php:113 response.headers.setCookie (создание новой корзины)
- БД: создание/обновление Cart, добавление CartItem внутри manager.addItemWithChanges (см. контроллер:214)
- Событие UI: emit CustomEvent — assets/catalog/src/features/add-to-cart/ui/button.ts:137 window.dispatchEvent(new CustomEvent('cart:updated', { detail }))

## repro

1) Открыть страницу товара → убедиться в наличии `data-module="add-to-cart"` (templates/catalog/product/show.html.twig:291)
2) Клик по кнопке → фронтенд: assets/catalog/src/features/add-to-cart/ui/button.ts:89 handleClick
3) Отправка запроса → assets/catalog/src/features/add-to-cart/api/index.ts:114 POST /api/cart/items
4) Бэкенд выполняет addItem → src/Controller/Api/CartApiController.php:110 (валидации/идемпотентность/manager)
5) Ответ 201 + заголовки ETag/Cart-* и установка/продление cookie корзины (CartContext выше)

## links

- Шаблон товара: templates/catalog/product/show.html.twig
- Сетка категории: templates/catalog/category/_grid.html.twig
- Регистрация модуля: assets/catalog/src/app/registry.ts
- UI‑кнопка: assets/catalog/src/features/add-to-cart/ui/button.ts
- API (frontend): assets/catalog/src/features/add-to-cart/api/index.ts
- Контроллер: src/Controller/Api/CartApiController.php
- Ответ/заголовки: src/Http/CartResponse.php
- Cookie/контекст корзины: src/Service/CartContext.php, src/Http/CartCookieFactory.php
- Документация: docs/cart-api-examples.md, README-cart-api-optimization.md

## summary

Клик по кнопке `data-module="add-to-cart"` ведёт к `handleClick` и вызову `addToCart`, который отправляет POST `/api/cart/items`. Бэкенд `addItem` проверяет вход/предикаты/идемпотентность, выполняет `manager.addItemWithChanges`, отвечает 201 с заголовками и продлевает cookie корзины через `CartContext`.

## TODO

- Нет


