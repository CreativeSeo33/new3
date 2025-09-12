### Архитектура корзины: устройство, флоу, особенности

Версия документа: 2025-09-12

---

## Обзор

- **Backend (Symfony):** `Cart`, `CartItem`, репозитории, менеджер `CartManager`, контексты `CartContext`/`DeliveryContext`/`CheckoutContext`, калькуляторы `CartCalculator`/`LivePriceCalculator`, HTTP-утилиты `CartResponse`/`CartEtags`/`CartWriteGuard`, идемпотентность `IdempotencyService` + `CartIdempotency`, блокировки `CartLockService`.
- **API:** `GET /api/cart`, `POST /api/cart/items`, `PATCH /api/cart/items/{itemId}`, `DELETE /api/cart/items/{itemId}`, `DELETE /api/cart`, `PATCH /api/cart` (pricingPolicy), `POST /api/cart/reprice`, `POST /api/cart/batch`.
- **Frontend:**
  - Stimulus: `assets/controllers/cart_counter_controller.js` — мини‑корзина/дропдаун, синхронизация ETag/версий, BroadcastChannel.
  - Catalog (FSD): `features/add-to-cart`, `features/cart-items-manager`, `widgets/cart-counter`, `shared/types/api.ts`, `shared/api/http.ts`.
- **Источник истины:** серверная корзина (`Cart`/`CartItem`). Фронтенд не пересчитывает бизнес‑логику (только отрисовка и делегирование на API).

---

## Данные и сущности

- `App\Entity\Cart`:
  - Поля: `id: Ulid`, `userId`, `token` (UUID для гостя, хранится в cookie `__Host-cart_id`), `currency`, `pricingPolicy` (`SNAPSHOT`|`LIVE`), `subtotal`, `discountTotal`, `total`, `version` (Optimistic Version), `createdAt/updatedAt/expiresAt`, `shippingMethod/cost/shipToCity/shippingData`, `items: OneToMany<CartItem>`.
  - Методы: `newGuest()`, `createNew(userId, ttl)`, `prolong()`, `ensureToken()`, `getTotalItemQuantity()`.
- `App\Entity\CartItem`:
  - Поля: `id (int)`, `cart`, `product`, `productName`, `unitPrice`, `effectiveUnitPrice`, `optionsPriceModifier`, `qty`, `rowTotal`, `optionsHash`, `selectedOptionsData`, `optionsSnapshot`, `pricedAt`, `optionAssignments (ManyToMany ProductOptionValueAssignment)`, `version`.
  - Уникальность: (`cart_id`, `product_id`, `options_hash`) — предотвращает дубликаты однотипных позиций.

Индексы и TTL:
- Корзина индексируется по `token`, `user_id`, `expires_at`. TTL по умолчанию 180 дней (контекст корзины) и 30 дней для `newGuest()`.

---

## Репозитории и блокировки

- `CartRepository`:
  - `findActiveByToken`, `findActiveById`, `findActiveByUserId` — подгружает связи (items, product, image, optionAssignments, option, value) и фильтрует по `expiresAt`.
  - `findItemForUpdate(cart, product, optionsHash)` — с `PESSIMISTIC_WRITE` для борьбы с гонками при upsert позиции.
  - `findItemByIdForUpdate(cart, itemId)` — валидирует принадлежность айтема корзине и берёт блокировку.
- `CartLockService` — неблокирующая попытка захвата lock (Symfony Lock) с ретраями и джиттером; ключ `cart:{cartId}`. Исключение `CartLockException` (423, `retryAfterMs`).

---

## Контексты и cookie

- `CartCookieFactory` — создаёт `__Host-cart_id` (Secure/HttpOnly/SameSite=Lax), TTL 180 дней. В проде всегда `Secure`.
- `CartContext`:
  - `getOrCreate(userId, response)` читает cookie токена → ищет корзину по токену → fallback legacy `cart_id` (ULID) → создаёт новую корзину под lock ключом `cart_creation_{ip+UA}` → выставляет cookie с токеном.
  - `getOrCreateForWrite` — продлевает TTL, привязывает пользователя (если есть), `flush`.
- `DeliveryContext` — хранит контекст доставки в сессии `checkout.delivery`, синхронизирует в поля корзины (`shipToCity`, `shippingMethod`, `shippingData`).
- `CheckoutContext` — кладёт ссылку на корзину в сессию `checkout.cart`.

---

## Калькуляторы и ценообразование

- Политика цен: `pricingPolicy` у корзины:
  - `SNAPSHOT` — используем зафиксированные цены (`effectiveUnitPrice`/`pricedAt`).
  - `LIVE` — для отображения добавляются live‑поля `currentEffectiveUnitPrice/currentRowTotal/priceChanged`. При изменении qty в LIVE — пересчитывается `effectiveUnitPrice`.
- `CartCalculator`:
  - `recalculateTotalsOnly(cart)` — внутри критической секции: пересчитывает `rowTotal` и `subtotal` по текущему `effectiveUnitPrice`/`qty`.
  - `recalculateShippingAndDiscounts(cart)` — вне секции: вызывает `DeliveryService->quote(cart)` (если выбран метод), задаёт `shippingCost`, пока `total = subtotal - discountTotal` (доставка отображается отдельно).
  - `recalculate(cart)` — полная (back‑compat).
- `LivePriceCalculator`:
  - Если есть опции — берёт максимум из `salePrice/price` выбранных назначений; иначе базовая цена товара (`effectivePrice|price`).

---

## Менеджер корзины и транзакции

- `CartManager` — все мутации под lock + транзакцией (`wrapInTransaction`):
  - `executeWithLock(cart, op)`:
    - Захват `CartLockService` с профилем (ttl/attempts/jitter).
    - Внутри транзакции: DB lock timeout под платформу (PG/Maria/MySQL), `PESSIMISTIC_WRITE` на корзине, вызывается операция, быстрый пересчёт totals, `updatedAt`, `flush`.
    - После выхода — `recalculateShippingAndDiscounts`, диспатч `CartUpdatedEvent`.
  - `addItem(cart, productId, qty, optionAssignmentIds)`:
    - Проверки: валидность входа; наличие товара/стока (`InventoryService`), `optionsHash` (md5 отсортированных assignmentIds).
    - Поиск существующей строки в памяти → в БД (`findItemForUpdate`). Если есть — увеличение `qty` (с валидацией стока по опциям). Если нет — создание `CartItem` с фиксацией базовой/опционной цены, снапшотами и связями; ранний `flush` ловит `1062` и выполняет merge.
  - `updateQty(cart, itemId, qty)` — при `qty<=0` → remove, иначе проверка стока с учётом опций, пересчёт `rowTotal`. В `LIVE` пересчитывает `effectiveUnitPrice`.
  - `removeItem(cart, itemId)` — удаляет и из ORM коллекции (для корректных totals).
  - `clearCart(cart)` — удаляет все `CartItem` и очищает коллекцию.
  - `merge(target, source)` — переносит строки (с учётом `optionsHash`), копирует снапшоты/связи, истекает `source`, чистит `token`.
  - Варианты `*WithChanges` — возвращают `changes` для delta‑ответов.
  - `executeBatch(cart, operations, atomic)` — выполняет список `add|update|remove` с либо атомарностью, либо best‑effort; возвращает `results`, `changes`, `totals`.

Ошибки/конфликты:
- Конфликты версий/дедлоки ретраятся (экспоненциальный backoff). При исчерпании — контроллер отвечает `409 version_conflict`.
- `InsufficientStockException` → `409 insufficient_stock` + `availableQuantity`.
- `CartItemNotFoundException` → `404 cart_item_not_found`.
- Занятая корзина (`CartLockException`) → `423 cart_busy` + `retryAfterMs`.

---

## HTTP слой, версии и ETag

- `CartWriteGuard` (запись):
  - Принимает либо `If-Match: "W/…"`, либо `version` (в body/json или query). Если не совпало — `412 precondition_failed` с актуальной корзиной.
  - В совместимом режиме может позволить запись без предикатов (конструкторный флаг).
- `CartEtags`:
  - ETag: `W/"cart:{id}.{version}.{updated_at_ts}"`.
- `CartResponse`:
  - Формирует payload в режиме `full|summary|delta` (см. CartDeltaBuilder).
  - Для лёгких ответов и некоторых методов может вернуть `204 No Content` (тело отсутствует, но заголовки `ETag`, `Cart-Version`, totals выставляются).

Режим ответа выбирается заголовком `Prefer` или query `view`:
- `Prefer: return=representation; profile="cart.full|cart.summary"` или `return=minimal; profile="cart.delta"`.

---

## Идемпотентность

- Поддерживается заголовок `Idempotency-Key` для write‑эндпоинтов (`POST /api/cart/items`, `PATCH /items/{id}`, `DELETE /items/{id}`, `DELETE /api/cart`, `POST /api/cart/batch`, `POST /api/cart/reprice`, `PATCH /api/cart`).
- `IdempotencyRequestHasher` строит канонический хеш тела/пути/метода (с нормализацией массивов опций/типов).
- `IdempotencyService` (`cart_idempotency` таблица) хранит состояние `processing|done` с `http_status`, `response_data`, TTL 48 ч., защита от гонок `unique(idempotency_key)` с сценариями:
  - `replay` — мгновенно вернёт ранее сохранённый ответ, пометит заголовками `Idempotency-Replay/Idempotency-Expires`.
  - `conflict` — тот же ключ, но другой `requestHash` → `409`.
  - `in_flight` — активный запрос с тем же ключом → `409` + `Retry-After`.
  - Устаревшие `processing` записи захватываются `tryTakeOverStale`.

---

## Контроллер API: эндпоинты и сериализация

`App\Controller\Api\CartApiController`:

- `GET /api/cart` → `serializeCart(cart, delivery)` возвращает полную корзину. Перед ответом рассчитывается доставка (`DeliveryService->calculateForCart`). Ставятся `ETag`, `Cache-Control`.
- `POST /api/cart/items` (body: `{productId, qty, optionAssignmentIds[]?}`)
  - Precondition (If-Match | version), Idempotency, `CartManager->addItemWithChanges()`.
  - Режим ответа авто (`delta` по умолчанию для write) или выбран заголовком.
  - Ошибки: 409 insufficient_stock, 423 cart_busy, 409 version_conflict, 422 domain.
- `PATCH /api/cart/items/{itemId}` (body: `{qty}`) — аналогично, вызывает `updateQtyWithChanges()`.
- `DELETE /api/cart/items/{itemId}` — `removeItemWithChanges()`; в `delta` режиме возможен `204`.
- `DELETE /api/cart` — `clearCartWithChanges()`; всегда `204`.
- `PATCH /api/cart` (body: `{pricingPolicy: SNAPSHOT|LIVE}`) — меняет политику, `flush`, full‑ответ.
- `POST /api/cart/reprice` — для всех позиций обновляет снепшот `effectiveUnitPrice` live‑ценами, пересчитывает корзину и доставку; full‑ответ.
- `POST /api/cart/batch` — список операций `{op: add|update|remove, ...}`; `atomic` true/false, Idempotency, возвращает `results`, `changedItems`, `removedItemIds`, `totals`.

Особенности `serializeCart()`:
- Включает `totalItemQuantity`, lightweight-URL первой картинки (`firstImageUrl/firstImageSmUrl`) для мини‑корзины, упорядочивает selectedOptions по `Option.sortOrder`. Для `LIVE` добавляет live‑поля на уровне item.

---

## Frontend: точки интеграции

### Stimulus (`cart_counter_controller.js`)

- Привязан к элементу мини‑корзины. Источники обновлений:
  - Событие `cart:updated` (dispatch из FSD‑модулей) — применяет `count`, `subtotal`, перерисовывает items при наличии, обновляет доставку/итого.
  - Периодический `refresh()` (опционально `data-cart-counter-poll-value`) — `GET /api/cart` (Accept JSON), учитывает `ETag` (сохраняет для If‑Match при записи через другие клиенты), lazy‑load списка при наведении.
  - BroadcastChannel `cart` и `localStorage` для мульти‑вкладок.

Контент, который читает из ответа:
- `version`, `totalItemQuantity` как счётчик; `subtotal`; `items` для верстки; `shipping.cost` и `shipping.data.term`.

### FSD Catalog

- `features/add-to-cart/api`:
  - Обёртки над `/api/cart` с типами и `Prefer` заголовками (`full|summary|delta`), `Idempotency-Key`, `If-Match`.
  - `executeBatchOperations` для `/api/cart/batch`.
- `features/add-to-cart/ui/button.ts`:
  - Собирает `optionAssignmentIds` из формы, генерирует идемпотентный ключ на попытку, вызывает `addToCart(..., { responseMode: 'full' })`.
  - Dispatch `window.dispatchEvent(new CustomEvent('cart:updated', { detail: cart }))` (+ `count` для совместимости), UI‑модалки успеха/ошибки склада.
- `features/cart-items-manager`:
  - `ui/manager.ts` — изменение количества и удаление строки в корзине (страница корзины). По умолчанию использует delta‑режим и при 204/ошибках подтягивает полную корзину для корректного прайсинга/доставки. Обновляет DOM, затем диспатчит `cart:updated`.
  - `api/index.ts` — удобные функции для delta/full режимов c fallback’ами и обработкой `412/409/428`.
- `widgets/cart-counter` — слушает `cart:updated`, умеет подтянуть summary `GET /api/cart` с `Prefer: cart.summary` и обновить компактный UI.
- `shared/types/api.ts` — типы `Cart`, `CartItem`, `CartDelta`, `CartSummary` и др.

Важно:
- Фронтенд НЕ вычисляет бизнес‑логику (скидки, доставка, live‑цены). Он только отображает данные и инициирует запросы.
- Счётчик корзины берётся из `totalItemQuantity`/`totals` — не суммировать qty вручную, если бэкенд уже прислал поле (в коде предусмотрен fallback на сумму, но правилом проекта источник истины — бэкенд).

---

## Цепочки вызовов (упрощённо)

### Добавление товара
1) UI `AddToCartButton` → `addToCart(productId, qty, optionAssignmentIds, { Prefer })` (+ `Idempotency-Key`).
2) API `POST /api/cart/items` → `CartContext->getOrCreateForWrite` → `CartWriteGuard->assertPrecondition` → `IdempotencyService->begin` → `CartManager->addItemWithChanges` → `executeWithLock` → `InventoryService->assertAvailable` → upsert `CartItem` → `CartCalculator->recalculateTotalsOnly` → `flush` → вне лока `recalculateShippingAndDiscounts` → `IdempotencyService->finish` → `CartResponse->withCart` (`delta` по умолчанию).
3) UI получает ответ → диспатчит `cart:updated` → Stimulus/виджеты обновляют UI.

### Обновление количества
1) UI (страница корзины) `CartItemsManager` → `updateCartItemQuantity(itemId, qty, { Prefer delta, If-Match? })`.
2) API `PATCH /api/cart/items/{itemId}` → guard/idempotency → `CartManager->updateQtyWithChanges` (LIVE: пересчёт `effectiveUnitPrice`) → ответ `delta|204`.
3) UI обновляет строку из delta, подтягивает полную корзину для доставки/итого, диспатчит `cart:updated`.

### Удаление позиции
1) UI `removeCartItem(itemId, { Prefer delta })`.
2) API `DELETE /api/cart/items/{itemId}` → guard/idempotency → `CartManager->removeItemWithChanges` → `delta|204`.
3) UI убирает строку, обновляет totals (через full fetch), диспатчит `cart:updated`.

### Очистка корзины
1) UI `clearCart({ Prefer delta })`.
2) API `DELETE /api/cart` → guard/idempotency → `clearCartWithChanges` → всегда `204`.

### Смена политики ценообразования
1) UI `PATCH /api/cart { pricingPolicy }`.
2) API валидирует значение, `flush`, пересчитывает доставку, возвращает `full`.

### Reprice
1) UI `POST /api/cart/reprice`.
2) API обновляет `effectiveUnitPrice` у позиций live‑ценами, пересчитывает корзину и доставку, возвращает `full`.

### Batch
1) UI `POST /api/cart/batch { operations[], atomic }` (+ `Idempotency-Key`).
2) API исполняет под lock, возвращает `results`, `changedItems`, `removedItemIds`, `totals` (для частичного успеха — 207 или 400 при `atomic=true`).

---

## Протоколы и контракты

Заголовки клиента:
- `Prefer`: управление объёмом данных (`cart.full|cart.summary|cart.delta`).
- `If-Match`: строгая защита от потери обновлений (рекомендуется для write).
- `Idempotency-Key`: обязателен для повторяемых write‑операций с риск‑повтором.

Статусы ответов:
- `200 OK` (полные/summary/некоторые delta), `201 Created` (add), `204 No Content` (оптимизированные write), `207 Multi-Status` (batch partial),
  `409` (stock/версия/дедлок), `412` (precondition_failed), `423` (cart_busy), `428` (precondition_required), `404` (item not found), `422` (domain).

---

## Edge cases и особенности

- Дубликаты позиций из‑за гонки вставки ловятся `UniqueConstraintViolationException` и мержатся по `optionsHash`.
- В `LIVE` режиме `effectiveUnitPrice` может отличаться от зафиксированного: фронтенд подсвечивает `priceChanged` и использует live‑поля для отображения.
- Доставка считается вне критической секции, чтобы не держать lock долго.
- Cookie формат мигрирован на токен `__Host-cart_id`; поддерживается fallback на legacy `cart_id` (ULID) при чтении.
- Мини‑корзина в Stimulus поддерживает lazy load списка на hover и cross‑tab sync.

---

## Быстрый справочник эндпоинтов

- GET `/api/cart`
  - Query: `view=full|summary|delta` (альтернатива `Prefer`).
  - Ответ: полная корзина/summary.
- POST `/api/cart/items` body: `{ productId: int, qty: int, optionAssignmentIds?: int[] }`.
- PATCH `/api/cart/items/{itemId}` body: `{ qty: int }`.
- DELETE `/api/cart/items/{itemId}`.
- DELETE `/api/cart`.
- PATCH `/api/cart` body: `{ pricingPolicy: "SNAPSHOT"|"LIVE" }`.
- POST `/api/cart/reprice`.
- POST `/api/cart/batch` body: `{ operations: Operation[], atomic: bool }`.

Пример delta запроса (обновление qty):
```http
PATCH /api/cart/items/123 HTTP/1.1
Prefer: return=minimal; profile="cart.delta"
If-Match: "W/\"cart:01HZ...\""    ; или version в теле
Idempotency-Key: cart-try-abc123
Content-Type: application/json

{ "qty": 3 }
```

---

## Рекомендации по использованию на фронте

- Для write используйте `Prefer: cart.delta` и читайте заголовки `Cart-Version`/totals при `204`.
- При изменении позиции после `204` — подтяните `GET /api/cart` (одним запросом) и диспатчите `cart:updated` для унифицированного UI‑обновления.
- Для UX‑обновления мини‑корзины допускается `Prefer: cart.summary`.
- Перед записью передавайте `If-Match` (или актуальную `version` в теле/квери), чтобы избегать случайных перезаписей.
- Всегда используйте `Idempotency-Key` при повторяемых действиях пользователя (клики/ретраи), ключ уникален на попытку.

---

## Где искать код

- Backend:
  - Сущности: `src/Entity/Cart.php`, `src/Entity/CartItem.php`, `src/Entity/CartIdempotency.php`.
  - Репозитории: `src/Repository/CartRepository.php`, `src/Repository/CartItemRepository.php`, `src/Repository/CartIdempotencyRepository.php`.
  - Сервисы: `src/Service/CartManager.php`, `src/Service/CartCalculator.php`, `src/Service/LivePriceCalculator.php`, `src/Service/CartLockService.php`, `src/Service/InventoryService.php`.
  - Контексты/HTTP: `src/Service/CartContext.php`, `src/Service/DeliveryContext.php`, `src/Http/CartCookieFactory.php`, `src/Http/CartResponse.php`, `src/Http/CartEtags.php`, `src/Http/CartWriteGuard.php`.
  - Контроллер: `src/Controller/Api/CartApiController.php`.
  - Идемпотентность: `src/Service/Idempotency/*`, `src/Entity/CartIdempotency.php`.
- Frontend:
  - Stimulus: `assets/controllers/cart_counter_controller.js`.
  - FSD: `assets/catalog/src/features/add-to-cart/*`, `assets/catalog/src/features/cart-items-manager/*`, `assets/catalog/src/widgets/cart-counter`, `assets/catalog/src/shared/types/api.ts`, `assets/catalog/src/shared/api/http.ts`.


