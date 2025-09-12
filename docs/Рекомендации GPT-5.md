### Баги и логические проблемы

- Баг: неправильное удаление айтема в `App\Entity\Cart::removeItem()` — связь не зануляется (orphanRemoval не сработает корректно).
```168:173:src/Entity/Cart.php
	public function removeItem(CartItem $item): void
	{
		if ($this->items->removeElement($item)) {
			if ($item->getCart() === $this) {
				$item->setCart($this); // keep relation consistent for Doctrine orphanRemoval
			}
		}
	}
```
Правильно:
```php
if ($this->items->removeElement($item)) {
	if ($item->getCart() === $this) {
		$item->setCart(null);
	}
}
```

- Баг: утечка отладочного вывода в проде.
```70:77:src/Service/CartCalculator.php
// ...
$total = max(0, $cart->getSubtotal() - $discountTotal);

dump($cart->getSubtotal() );

$cart->setDiscountTotal($discountTotal);
$cart->setTotal($total);
```
Нужно удалить `dump()`.

- Баг фронта (Stimulus): из‑за области видимости `res` обращение к заголовкам `ETag` упадёт.
```98:132:assets/controllers/cart_counter_controller.js
let data;
try {
  const res = await fetch(this.urlValue, {/*...*/});
  // ...
} catch { /*...*/ }
try { this.cartEtag = res.headers.get('ETag') || null; } catch { this.cartEtag = null; }
```
Правка (сохранить `res` вне try):
```js
let data, res;
try {
  res = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!res.ok) { this.hideSpinner(); return; }
  data = await res.json();
} catch (e) { this.hideSpinner(); return; }
this.cartEtag = res?.headers?.get('ETag') || null;
```
Аналогично в `loadItems()`.

- Несогласованность типов: в TS `CartItem.id: string` (комментарий про ULID), а в бэке `CartItem.id` — `int`. Исправить тип на `number`:
```24:52:assets/catalog/src/shared/types/api.ts
export interface CartItem {
-  id: string; // Теперь ULID в base32 формате
+  id: number;
  // ...
}
```

- Потенциальная потеря обновлений: `CartWriteGuard` по умолчанию не требует предикат (If‑Match/version). Это оставляет окно для гонок обновлений.

- Дублирование логики cookie/legacy ULID в `CartManager::getOrCreateCurrent()` и `CartContext` → разноска источников истины для токена корзины.

### Риски безопасности
- Конкурентные записи без предикатов (If‑Match или `version`) → потеря обновлений. Включить обязательные предикаты на write.
- Idempotency: хранит `response_data` в БД как JSON. Если когда‑то туда попадут PII — нужен аудит полей/ретеншн и шифрование на уровне столбца (по необходимости).
- Lock по ключу `md5(IP+UA)` при создании корзины: коллизии для NAT/одинакового UA могут блокировать конкурирующих пользователей. Лучше использовать sessionId/traceId.
- Фронт считает итог (subtotal+shipping) на клиенте/шаблоне — это бизнес‑логика не на бэке. Нужно возвращать `grandTotal` с бэка и только отображать [[memory:6307972]].
- Микрориск XSS в мини‑корзине теоретически нивелирован вашим `escapeHtml`, но проверьте все места, где используется `innerHTML`.

### Архитектурные шероховатости
- Доставка/итог: пересчёт доставки есть и в `CartCalculator->recalculateShippingAndDiscounts()`, и в `CartApiController->getCart()` через `DeliveryService->calculateForCart()`. Дублирование и потенциальные расхождения.
- `total` сейчас не включает доставку, шаблоны и фронт суммируют. Лучше: на бэке отдавать `grandTotal`, а `total` оставить как `itemsTotal` (или переименовать поле для ясности).
- Лишний `error_log()` в `CartManager` — заменить на `LoggerInterface`.

### Предлагаемые улучшения (минимальные и безопасные)
- Исправить `removeItem` и убрать `dump` — без изменения поведения.
- Включить требование предикатов записи:
```yaml
# config/services.yaml
services:
    App\Http\CartWriteGuard:
        arguments:
            $requirePrecondition: true
```
Клиентам передавать `If-Match` (или `version`) на все `POST/PATCH/DELETE`.

- Добавить `grandTotal` в ответы API, чтобы фронт не считал итого:
```857:975:src/Controller/Api/CartApiController.php
return [
  // ...
  'subtotal' => $cart->getSubtotal(),
  'discountTotal' => $cart->getDiscountTotal(),
  'total' => $cart->getTotal(), // itemsTotal
  'grandTotal' => $cart->getSubtotal() + ($deliveryResult?->cost ?? $cart->getShippingCost()), // NEW
  // ...
];
```
И в билдер:
```121:170:src/Service/CartDeltaBuilder.php
return [
  // ...
  'subtotal' => $cart->getSubtotal(),
  'discountTotal' => $cart->getDiscountTotal(),
  'total' => $cart->getTotal(),
+ 'grandTotal' => $cart->getSubtotal() + ($cart->getShippingCost() ?? 0),
  // ...
];
```
А для delta/summary:
```85:116:src/Service/CartDeltaBuilder.php
return [
  'version' => $cart->getVersion(),
  // ...
  'totals' => [
    'itemsCount' => $cart->getItems()->count(),
    'subtotal' => $cart->getSubtotal(),
    'discountTotal' => $cart->getDiscountTotal(),
    'total' => $cart->getTotal(),
+   'grandTotal' => $cart->getSubtotal() + ($cart->getShippingCost() ?? 0),
  ],
];
```
Шаблон можно мягко переключить (не ломающий вариант):
```104:121:templates/catalog/cart/index.html.twig
{% set grand = cart.grandTotal is defined ? cart.grandTotal : (cart.subtotal + (delivery.cost ?? 0)) %}
...
<div class="border-t pt-2 font-semibold">
  Итого: <span id="cart-total" data-cart-total>{{ format_price(grand) }}</span> {{ cart.currency }}
</div>
```

- Устранить дублирование доставки: единообразно считать стоимость в одном месте (лучше — в `CartCalculator->recalculateShippingAndDiscounts()`), а в `GET /api/cart` только сериализовать (без повторной калькуляции). Тогда `DeliveryService->calculateForCart` можно вызывать при write‑операциях и в плановом reprice.

- Усилить ключ блокировки при создании корзины (уменьшить коллизии и риск DoS):
```php
// src/Service/CartContext.php
$sessionId = $this->requestStack->getSession()?->getId() ?? bin2hex(random_bytes(8));
$lockKey = 'cart_creation_' . sha1($sessionId . '|' . ($request->headers->get('User-Agent') ?? ''));
$lock = $this->lockFactory->createLock($lockKey, 5.0); // TTL <= 5s
if (!$lock->acquire(false)) {
    usleep(random_int(25, 120) * 1000);
    if (!$lock->acquire(false)) throw new \RuntimeException('Cart creation is busy, retry later');
}
```

- Перевести настройки `CartCookieFactory` из дефолтов в конфиг (не хардкодить):
```yaml
# config/services.yaml
parameters:
    app.cart.cookie.name: 'cart_id'
    app.cart.cookie.ttl_days: 180
    app.cart.cookie.host_prefix: true
    app.cart.cookie.force_secure_in_prod: true
    app.cart.cookie.same_site: 'lax'

services:
    App\Http\CartCookieFactory:
        arguments:
            $forceSecureInProd: '%app.cart.cookie.force_secure_in_prod%'
            $useHostPrefix: '%app.cart.cookie.host_prefix%'
            $cookieName: '%app.cart.cookie.name%'
            $ttlDays: '%app.cart.cookie.ttl_days%'
            $domain: null
            $sameSite: '%app.cart.cookie.same_site%'
```

- Заменить `error_log` в `CartManager` на `LoggerInterface`:
```49:49:src/Service/CartManager.php
-        private LivePriceCalculator $livePrice,
+        private LivePriceCalculator $livePrice,
+        private \Psr\Log\LoggerInterface $logger,
...
-    error_log("CartManager: legacy ULID fallback used for cart: " . $cart->getIdString());
+    $this->logger->notice('CartManager: legacy ULID fallback used for cart', ['cartId' => $cart->getIdString()]);
```

- Рейтлимит на write эндпоинты (минимальный пример):
```yaml
# config/packages/rate_limiter.yaml
framework:
  rate_limiter:
    cart_write:
      policy: 'token_bucket'
      limit: 20
      rate: { interval: '1 minute', amount: 20 }
```
И применить в контроллере через атрибут/файрвол (или глобальный listener).

- FSD/виджет: перестать вычислять итого из DOM (и парсить текст) — использовать `grandTotal` из API. Это снимет часть фронтовых багов и дрейфы значений [[memory:6307972]].

### Неблокирующие замечания
- Именование: `total` как itemsTotal вводит в заблуждение. Рассмотреть `itemsTotal` + `grandTotal` явно.
- `md5` для `optionsHash` достаточно, но если хотите избежать «крипто‑поводов», можно перейти на `hash('xxh128', ...)` или `sha1`.
- Проверить, что `CartIdempotency` очищается по TTL (фоновая задача/cron) — иначе таблица разрастётся.

Если хотите — могу сразу сделать безопасные правки по четырём пунктам: `removeItem()`, убрать `dump()`, фиксы Stimulus `res`, добавить `grandTotal` в ответы (без изменения текущих потребителей).