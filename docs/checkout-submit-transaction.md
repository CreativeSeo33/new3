# Checkout submit: транзакция, синхронизация корзины и очистка контекстов

Изменения внедрены согласно delivery-audit п.7.2:

- Перед оформлением используем `CartManager::getOrCreateForWrite()` для синхронизации доставки и пересчёта.
- Весь процесс создания заказа обёрнут в транзакцию `EntityManager::wrapInTransaction()`.
- По успешному завершению транзакции корзина помечается истёкшей (`Cart::setExpiresAt(new DateTimeImmutable('-1 second'))`).
- После транзакции выполняется очистка `CheckoutContext::clear()`.

Ключевые edits:

```12:43:src/Controller/Catalog/CheckoutController.php
$cart = $cartManager->getOrCreateForWrite($userId);
```

```85:199:src/Controller/Catalog/CheckoutController.php
$em->wrapInTransaction(function() use (...) {
  // создание Order, OrderCustomer, OrderDelivery, OrderProducts
  $cart->setExpiresAt(new \DateTimeImmutable('-1 second'));
  $em->flush();
});
$checkout->clear();
```

Нюансы:
- Валидация доставки провайдером выполняется внутри транзакции; при ошибке — 400 и откат.
- Ответ возвращает `id`, `orderId`, `redirectUrl` из созданного заказа.
- GET `/checkout` оставлен без пересчёта (как и раньше) — write-путь применён для POST.
