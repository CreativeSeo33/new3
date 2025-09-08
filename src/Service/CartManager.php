<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\Entity\ProductOptionValueAssignment;
use App\Service\PriceNormalizer;
use App\Service\CartLockException;

/**
 * CartManager - сервис для управления корзиной покупок
 *
 * РЕФАКТОРИНГ: Класс был разбит на более мелкие методы для улучшения читаемости и поддержки.
 * Основные изменения:
 * - addItem() разбит на validateAddItemInput(), doAddItem(), createNewCartItem() и т.д.
 * - Добавлена общая логика executeWithLock() для всех операций
 * - Улучшена обработка опций товара
 * - Добавлена валидация входных параметров
 */

final class CartManager
{
    public function __construct(
		private EntityManagerInterface $em,
		private CartRepository $carts,
		private ProductRepository $products,
        private CartCalculator $calculator,
		private InventoryService $inventory,
		private CartLockService $lock,
		private RequestStack $requestStack,
		private EventDispatcherInterface $events,
        private DeliveryContext $deliveryContext,
        private CheckoutContext $checkoutContext,
    ) {}

    public function getOrCreateCurrent(?int $userId): Cart
    {
        // Этот метод сохранен для обратной совместимости
        // Рекомендуется использовать CartContext напрямую

        if ($userId) {
            $cart = $this->carts->findActiveByUserId($userId);
        } else {
            // Для гостей пробуем найти корзину по cookie cart_id
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $cartIdCookie = $request->cookies->get('cart_id');
                if ($cartIdCookie) {
                    try {
                        $cartId = \Symfony\Component\Uid\Ulid::fromString($cartIdCookie);
                        $cart = $this->carts->findActiveById($cartId);
                    } catch (\InvalidArgumentException) {
                        $cart = null;
                    }
                }
            }
        }

        if (!$cart) {
            $cart = Cart::createNew($userId, (new \DateTimeImmutable())->modify('+180 days'));
            $this->em->persist($cart);
        }

        // Синхронизация контекста доставки и пересчет
        $this->deliveryContext->syncToCart($cart);
        $this->calculator->recalculate($cart);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        // Сохраняем ссылку на корзину в сессии checkout.cart
        $this->checkoutContext->setCartRefFromCart($cart);

        return $cart;
    }

	/**
	 * Генерирует уникальный хеш для набора опций
	 */
	private function generateOptionsHash(array $optionAssignmentIds): string
	{
		// Приводим ID к int, удаляем дубликаты и сортируем
		$ids = array_values(array_unique(array_map('intval', $optionAssignmentIds)));
		sort($ids, SORT_NUMERIC);
		return md5(implode(',', $ids));
	}

	public function addItem(Cart $cart, int $productId, int $qty, array $optionAssignmentIds = []): Cart
	{
		$this->validateAddItemInput($productId, $qty);

		return $this->executeWithLock($cart, function() use ($cart, $productId, $qty, $optionAssignmentIds) {
			return $this->doAddItem($cart, $productId, $qty, $optionAssignmentIds);
		});
	}

	private function validateAddItemInput(int $productId, int $qty): void
	{
		if ($productId <= 0) {
			throw new \InvalidArgumentException('Product ID must be positive');
		}
		if ($qty <= 0) {
			throw new \InvalidArgumentException('Quantity must be positive');
		}
	}

	/**
	 * Выполняет операцию под блокировкой с суженной критической секцией
	 */
	private function executeWithLock(Cart $cart, callable $operation, array $lockOpts = []): Cart
	{
		// Настройки лока по умолчанию для обычных операций
		$defaultOpts = [
			'ttl' => 3.0,
			'attempts' => 3,
			'minSleepMs' => 25,
			'maxSleepMs' => 120,
		];
		$opts = array_merge($defaultOpts, $lockOpts);

		$result = $this->retryOnConflict(function() use ($cart, $operation, $opts) {
			return $this->lock->withCartLock($cart, function() use ($cart, $operation) {
				return $this->em->wrapInTransaction(function() use ($cart, $operation) {
					// Устанавливаем таймаут ожидания блокировок в зависимости от типа БД
					$connection = $this->em->getConnection();
					$platform = $connection->getDatabasePlatform()->getName();

					try {
						if ($platform === 'postgresql') {
							$connection->executeStatement("SET LOCAL lock_timeout = '2s'");
						} elseif ($platform === 'mysql') {
							$connection->executeStatement("SET innodb_lock_wait_timeout = 2");
						}
					} catch (\Doctrine\DBAL\Exception\DriverException $e) {
						// Fallback: если переменная не поддерживается, просто продолжаем без таймаута
						if (str_contains($e->getMessage(), 'Unknown system variable') ||
							str_contains($e->getMessage(), 'Unknown variable')) {
							// Игнорируем ошибку и продолжаем без установки таймаута
							error_log("Database lock timeout not supported, continuing without timeout: " . $e->getMessage());
						} else {
							// Для других ошибок пробрасываем исключение
							throw $e;
						}
					}

					$this->em->lock($cart, LockMode::PESSIMISTIC_WRITE);
					$operation();
					// Быстрый пересчет только итогов без внешних IO
					$this->calculator->recalculateTotalsOnly($cart);
					$cart->setUpdatedAt(new \DateTimeImmutable());
					$this->em->flush();
					return $cart;
				});
			}, $opts);
		});

		// ВНЕ лока/транзакции — тяжелые части (доставка/LIVE), события
		$this->calculator->recalculateShippingAndDiscounts($cart);
		$this->events->dispatch(new \App\Event\CartUpdatedEvent($cart->getIdString()));

		return $result;
	}

	/**
	 * Профиль настроек блокировки для merge операций (более долгий)
	 */
	private function getMergeLockOptions(): array
	{
		return [
			'ttl' => 8.0,
			'attempts' => 5,
			'minSleepMs' => 50,
			'maxSleepMs' => 200,
		];
	}

	/**
	 * Профиль настроек блокировки для простых операций (короткий)
	 */
	private function getSimpleLockOptions(): array
	{
		return [
			'ttl' => 2.0,
			'attempts' => 2,
			'minSleepMs' => 20,
			'maxSleepMs' => 80,
		];
	}

	/**
	 * Выполняет операцию с ретраями при конфликтах версий и дедлоках
	 */
	private function retryOnConflict(callable $fn, int $maxRetries = 3): mixed
	{
		$lastException = null;

		for ($i = 0; $i <= $maxRetries; $i++) {
			try {
				return $fn();
			} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
				$lastException = $e;

				if ($i === $maxRetries) {
					throw $e; // Последняя попытка - пробрасываем исключение
				}

				// Экспоненциальный backoff с джиттером
				$delayMs = (int)(50 * (2 ** $i) + random_int(0, 50));
				usleep($delayMs * 1000);
			}
		}

		// Это никогда не должно случиться, но для линтера
		throw $lastException ?? new \RuntimeException('Unexpected retry failure');
	}

	private function doAddItem(Cart $cart, int $productId, int $qty, array $optionAssignmentIds): void
	{
		$product = $this->products->find($productId);
		if (!$product) {
			throw new \DomainException('Product not found');
		}

		$this->inventory->assertAvailable($product, $qty);
		$optionsHash = $this->generateOptionsHash($optionAssignmentIds);
		$existingItem = $this->findExistingCartItem($cart, $product, $optionsHash);

		if ($existingItem) {
			$this->updateExistingItem($existingItem, $product, $qty);
		} else {
			$this->createNewCartItem($cart, $product, $qty, $optionAssignmentIds, $optionsHash);
		}
	}

	private function findExistingCartItem(Cart $cart, $product, ?string $optionsHash): ?CartItem
	{
		return $optionsHash
			? $this->carts->findItemForUpdateWithOptions($cart, $product, $optionsHash)
			: $this->carts->findItemForUpdate($cart, $product);
	}

	private function updateExistingItem(CartItem $item, $product, int $additionalQty): void
	{
		$newQty = $item->getQty() + $additionalQty;

		// Извлекаем optionAssignmentIds из существующего товара
		$optionAssignmentIds = $this->getOptionAssignmentIdsFromItem($item);

		$this->inventory->assertAvailable($product, $newQty, $optionAssignmentIds);
		$item->setQty($newQty);
	}

	private function createNewCartItem(Cart $cart, $product, int $qty, array $optionAssignmentIds, ?string $optionsHash): void
	{
		$item = new CartItem();
		$item->setCart($cart);
		$item->setProduct($product);
		$item->setProductName($product->getName() ?? '');
		$item->setQty($qty);

		$basePriceRub = PriceNormalizer::toRubInt($product->getEffectivePrice() ?? $product->getPrice() ?? 0);
		$item->setUnitPrice($basePriceRub);

		if (!empty($optionAssignmentIds)) {
			$this->applyProductOptions($item, $product, $optionAssignmentIds, $basePriceRub, $optionsHash);
		} else {
			$item->setEffectiveUnitPrice($basePriceRub);
			$item->setOptionsHash(null);
		}

		// Устанавливаем время фиксации цены
		$item->setPricedAt(new \DateTimeImmutable());

		$cart->addItem($item);
		$this->em->persist($item);
	}

	private function applyProductOptions(CartItem $item, $product, array $optionAssignmentIds, int $basePriceRub, ?string $optionsHash): void
	{
		$setPrices = [];
		$modifier = 0;
		$selectedOptionsData = [];
		$optionsSnapshot = [];

		foreach ($optionAssignmentIds as $assignmentId) {
			$assignment = $this->em->getRepository(ProductOptionValueAssignment::class)->find((int)$assignmentId);
			if (!$assignment || $assignment->getProduct()->getId() !== $product->getId()) {
				throw new \DomainException('Invalid option assignment');
			}

			$item->addOptionAssignment($assignment);

			$price = $assignment->getSalePrice() ?? $assignment->getPrice() ?? 0;
			$optionPriceRub = PriceNormalizer::toRubInt($price);

			// Логика setPrice: если опция задаёт цену, она влияет на базовую цену, а не на модификатор
			if ($assignment->getSetPrice() === true && $optionPriceRub > 0) {
				$setPrices[] = $optionPriceRub;
			} else {
				$modifier += $optionPriceRub;
			}

			$selectedOptionsData[] = $this->createOptionData($assignment, $optionPriceRub);
			$optionsSnapshot[] = $this->createOptionSnapshot($assignment);
		}

		// Если есть опции с setPrice, берём максимум из них как базовую цену
		$unitPrice = !empty($setPrices) ? max($setPrices) : $basePriceRub;

		$item->setUnitPrice($unitPrice);
		$item->setOptionsPriceModifier($modifier);
		$item->setSelectedOptionsData($selectedOptionsData);
		$item->setOptionsSnapshot($optionsSnapshot);
		$item->setOptionsHash($optionsHash);
		$item->setEffectiveUnitPrice($unitPrice + $modifier);
	}

	private function createOptionData($assignment, int $optionPriceRub): array
	{
		return [
			'assignmentId' => $assignment->getId(),
			'optionCode' => $assignment->getOption()->getCode(),
			'optionName' => $assignment->getOption()->getName(),
			'valueCode' => $assignment->getValue()->getCode(),
			'valueName' => $assignment->getValue()->getValue(),
			'price' => $optionPriceRub,
			'sku' => $assignment->getSku(),
		];
	}

	private function createOptionSnapshot($assignment): array
	{
		return [
			'assignment_id' => $assignment->getId(),
			'option_code' => $assignment->getOption()->getCode(),
			'option_name' => $assignment->getOption()->getName(),
			'value_code' => $assignment->getValue()->getCode(),
			'value_name' => $assignment->getValue()->getValue(),
			'price' => $assignment->getPrice(),
			'sale_price' => $assignment->getSalePrice(),
			'sku' => $assignment->getSku(),
			'original_sku' => $assignment->getOriginalSku(),
			'height' => $assignment->getHeight(),
			'bulbs_count' => $assignment->getBulbsCount(),
			'lighting_area' => $assignment->getLightingArea(),
			'attributes' => $assignment->getAttributes(),
		];
	}



	public function updateQty(Cart $cart, int $itemId, int $qty): ?Cart
	{
		if ($qty <= 0) {
			return $this->removeItem($cart, $itemId);
		}

		try {
			return $this->executeWithLock($cart, function() use ($cart, $itemId, $qty) {
				$this->doUpdateQty($cart, $itemId, $qty);
			});
		} catch (\DomainException) {
			return null; // Товар не найден
		}
	}

	private function doUpdateQty(Cart $cart, int $itemId, int $qty): void
	{
		$item = $this->carts->findItemByIdForUpdate($cart, $itemId);
		if (!$item) {
			throw new \DomainException('Item not found');
		}

		// Извлекаем optionAssignmentIds из товара
		$optionAssignmentIds = $this->getOptionAssignmentIdsFromItem($item);

		$this->inventory->assertAvailable($item->getProduct(), $qty, $optionAssignmentIds);
		$item->setQty($qty);
	}

	public function removeItem(Cart $cart, int $itemId): ?Cart
	{
		$itemRemoved = false;

		try {
			return $this->executeWithLock($cart, function() use ($cart, $itemId, &$itemRemoved) {
				$itemRemoved = $this->doRemoveItem($cart, $itemId);
			});
		} catch (\DomainException) {
			return null; // Товар не найден
		}
	}

	private function doRemoveItem(Cart $cart, int $itemId): bool
	{
		error_log("CartManager: doRemoveItem called for item {$itemId}, cart ID: {$cart->getIdString()}");

		// Пробуем найти товар напрямую через SQL, игнорируя проблемы с Doctrine
		$cartId = $cart->getId();
		$sql = 'SELECT ci.* FROM cart_item ci WHERE ci.cart_id = ? AND ci.id = ? LIMIT 1 FOR UPDATE';
		$result = $this->em->getConnection()->executeQuery($sql, [$cartId, $itemId])->fetchAssociative();

		if ($result) {
			error_log("CartManager: Found item {$itemId} via direct SQL");
			$item = $this->em->find(CartItem::class, $result['id']);
		} else {
			error_log("CartManager: Item {$itemId} not found via direct SQL");
			$item = null;
		}

		if (!$item) {
			// Попытка найти товар без учета корзины (для отладки)
			$item = $this->em->find(CartItem::class, $itemId);
			if ($item) {
				$itemCartId = $item->getCart() ? $item->getCart()->getId() : null;
				error_log("CartManager: Found item {$itemId} in cart {$itemCartId}, expected cart {$cartId}");
				// Не удаляем товар из другой корзины
				if ($itemCartId !== $cartId) {
					return false;
				}
			}
		}

		if ($item) {
			error_log("CartManager: Removing item ID {$itemId} from cart {$cart->getIdString()}");
			$this->em->remove($item);
			error_log("CartManager: Item removed, will flush in finishCartOperation");
			return true;
		}

		error_log("CartManager: Item ID {$itemId} not found in cart {$cart->getIdString()}");
		return false;
	}

	public function assignToUser(Cart $cart, int $userId): void
	{
		$cart->setUserId($userId);
		$cart->setToken(null); // Убираем token при присвоении пользователю
		$this->em->flush();
	}

	public function clearCart(Cart $cart): Cart
	{
		return $this->executeWithLock($cart, function() use ($cart) {
			$this->doClearCart($cart);
		});
	}

	private function doClearCart(Cart $cart): void
	{
		foreach ($cart->getItems() as $item) {
			$this->em->remove($item);
		}
	}

	public function merge(Cart $target, Cart $source): Cart
	{
		return $this->executeWithLock($target, function() use ($target, $source) {
			foreach ($source->getItems() as $srcItem) {
				// Ищем существующий товар с учетом опций
				$optionsHash = $srcItem->getOptionsHash();
				$existing = $optionsHash
					? $this->carts->findItemForUpdateWithOptions($target, $srcItem->getProduct(), $optionsHash)
					: $this->carts->findItemForUpdate($target, $srcItem->getProduct());

				$qty = $existing ? $existing->getQty() + $srcItem->getQty() : $srcItem->getQty();

				// Извлекаем optionAssignmentIds из исходного товара
				$optionAssignmentIds = $this->getOptionAssignmentIdsFromItem($srcItem);
				$this->inventory->assertAvailable($srcItem->getProduct(), $qty, $optionAssignmentIds);

				if ($existing) {
					$existing->setQty($qty);
				} else {
					$clone = new CartItem();
					$clone->setCart($target);
					$clone->setProduct($srcItem->getProduct());
					$clone->setProductName($srcItem->getProductName());
					$clone->setUnitPrice($srcItem->getUnitPrice());
					$clone->setQty($srcItem->getQty());

					// Копируем данные опций
					$clone->setOptionsPriceModifier($srcItem->getOptionsPriceModifier());
					$clone->setEffectiveUnitPrice($srcItem->getEffectiveUnitPrice());
					$clone->setOptionsHash($srcItem->getOptionsHash());
					$clone->setSelectedOptionsData($srcItem->getSelectedOptionsData());
					$clone->setOptionsSnapshot($srcItem->getOptionsSnapshot());

					// Копируем время фиксации цены
					$clone->setPricedAt($srcItem->getPricedAt());

					// Копируем связи с опциями
					foreach ($srcItem->getOptionAssignments() as $assignment) {
						$clone->addOptionAssignment($assignment);
					}

					$this->em->persist($clone);
				}
			}

			// Помечаем исходную корзину как истекшую и очищаем token
			$source->setExpiresAt(new \DateTimeImmutable('-1 day'));
			$source->setToken(null);

			foreach ($source->getItems() as $it) $this->em->remove($it);
		}, $this->getMergeLockOptions());

		// Для merge не диспатчим событие здесь - оно уже диспатчится в executeWithLock
		return $target;
	}

	/**
	 * Извлекает ID опций из CartItem
	 */
	private function getOptionAssignmentIdsFromItem(CartItem $item): array
	{
		$optionAssignments = $item->getOptionAssignments();
		if ($optionAssignments->isEmpty()) {
			return [];
		}

		return $optionAssignments->map(fn($assignment) => $assignment->getId())->toArray();
	}
}


