<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\Entity\ProductOptionValueAssignment;
use App\Service\PriceNormalizer;
use App\Service\CartLockException;
use App\Http\CartCookieFactory;
use App\Exception\CartItemNotFoundException;

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
        private CartCookieFactory $cookieFactory,
    ) {}

    public function getOrCreateCurrent(?int $userId): Cart
    {
        // Этот метод сохранен для обратной совместимости
        // Рекомендуется использовать CartContext напрямую

        $cart = null; // Инициализируем переменную

        if ($userId) {
            $cart = $this->carts->findActiveByUserId($userId);
        } else {
            // Для гостей пробуем найти корзину по cookie
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                // Читаем cookie: новый формат (__Host-cart_id) как токен
                $tokenCookie = $request->cookies->get($this->cookieFactory->getCookieName());
                // Fallback на legacy cookie (cart_id) как ULID для миграции
                $legacyCookie = $request->cookies->get('cart_id');

                // 1) Сначала пытаемся найти корзину по токену (новый формат)
                if ($tokenCookie) {
                    $cart = $this->carts->findActiveByToken($tokenCookie);
                }

                // 2) Fallback: legacy cookie как ULID (временная поддержка миграции)
                if (!$cart && $legacyCookie) {
                    try {
                        $cartId = Ulid::fromString($legacyCookie);
                        $cart = $this->carts->findActiveById($cartId);
                        if ($cart) {
                            // Логируем использование legacy fallback
                            error_log("CartManager: legacy ULID fallback used for cart: " . $cart->getIdString());
                        }
                    } catch (\InvalidArgumentException) {
                        // Игнорируем неверный формат ULID
                    }
                }
            }
        }

        if (!$cart) {
            $cart = Cart::createNew($userId, (new \DateTimeImmutable())->modify('+180 days'));
            $this->em->persist($cart);
        }

        // Для GET-запросов не выполняем мутации корзины
        // Синхронизация контекста доставки и пересчет выполняются только при модификации

        // Сохраняем ссылку на корзину в сессии checkout.cart
        $this->checkoutContext->setCartRefFromCart($cart);

        return $cart;
    }

    /**
     * Получает корзину для операций записи с синхронизацией и пересчетом
     */
    public function getOrCreateForWrite(?int $userId): Cart
    {
        $cart = $this->getOrCreateCurrent($userId);

        // Синхронизация контекста доставки и пересчет только при модификации
        $this->deliveryContext->syncToCart($cart);
        $this->calculator->recalculate($cart);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $cart;
    }

	/**
	 * Генерирует уникальный хеш для набора опций
	 * Возвращает пустую строку для товаров без опций
	 */
	public function generateOptionsHash(array $optionAssignmentIds): string
	{
		// Приводим ID к int, удаляем дубликаты и сортируем
		$ids = array_values(array_unique(array_map('intval', $optionAssignmentIds)));
		sort($ids, SORT_NUMERIC);

		// Для товаров без опций возвращаем пустую строку
		if (empty($ids)) {
			return '';
		}

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
	private function executeWithLock(Cart $cart, callable $operation, array $lockOpts = []): mixed
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
					$operationResult = $operation();
					// Быстрый пересчет только итогов без внешних IO
					$this->calculator->recalculateTotalsOnly($cart);
					$cart->setUpdatedAt(new \DateTimeImmutable());
					$this->em->flush();
					return $operationResult ?? $cart;
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

		try {
			if ($existingItem) {
				$this->updateExistingItem($existingItem, $product, $qty);
			} else {
				$this->createNewCartItem($cart, $product, $qty, $optionAssignmentIds, $optionsHash);
				$this->em->flush(); // Важный ранний flush для перехвата 1062
			}
		} catch (UniqueConstraintViolationException) {
			// Гонка: параллельно вставили такую же строку — мёрджим
			$conflict = $this->findExistingCartItem($cart, $product, $optionsHash);
			if ($conflict) {
				$this->updateExistingItem($conflict, $product, $qty);
			} else {
				throw new \RuntimeException('Unique constraint violation without conflict resolution');
			}
		}
	}

	private function findExistingCartItem(Cart $cart, $product, string $optionsHash): ?CartItem
	{
		// Сначала ищем в памяти (уже загруженные товары)
		foreach ($cart->getItems() as $item) {
			if ($item->getProduct()?->getId() === $product->getId()
				&& $item->getOptionsHash() === $optionsHash) {
				return $item;
			}
		}

		// Если не нашли в памяти, ищем в БД с блокировкой
		return $this->carts->findItemForUpdate($cart, $product, $optionsHash);
	}

	private function updateExistingItem(CartItem $item, $product, int $additionalQty): void
	{
		$newQty = $item->getQty() + $additionalQty;

		// Извлекаем optionAssignmentIds из существующего товара
		$optionAssignmentIds = $this->getOptionAssignmentIdsFromItem($item);

		$this->inventory->assertAvailable($product, $newQty, $optionAssignmentIds);
		$item->setQty($newQty);
	}

	private function createNewCartItem(Cart $cart, $product, int $qty, array $optionAssignmentIds, string $optionsHash): void
	{
		$item = new CartItem();
		$item->setCart($cart);
		$item->setProduct($product);
		$item->setProductName($product->getName() ?? '');
		$item->setQty($qty);
		$item->setOptionsHash($optionsHash);

		$basePriceRub = PriceNormalizer::toRubInt($product->getEffectivePrice() ?? $product->getPrice() ?? 0);
		$item->setUnitPrice($basePriceRub);

		if (!empty($optionAssignmentIds)) {
			$this->applyProductOptions($item, $product, $optionAssignmentIds, $basePriceRub, $optionsHash);
		} else {
			$item->setEffectiveUnitPrice($basePriceRub);
		}

		// Устанавливаем время фиксации цены
		$item->setPricedAt(new \DateTimeImmutable());

		$cart->addItem($item);
		$this->em->persist($item);
	}

	private function applyProductOptions(CartItem $item, $product, array $optionAssignmentIds, int $basePriceRub, string $optionsHash): void
	{
		$selectedOptionsData = [];
		$optionsSnapshot = [];
		$optionPrices = [];

		foreach ($optionAssignmentIds as $assignmentId) {
			$assignment = $this->em->getRepository(ProductOptionValueAssignment::class)->find((int)$assignmentId);
			if (!$assignment || $assignment->getProduct()->getId() !== $product->getId()) {
				throw new \DomainException('Invalid option assignment');
			}

			$item->addOptionAssignment($assignment);

			// Получаем цену опции (salePrice имеет приоритет)
			$price = $assignment->getSalePrice() ?? $assignment->getPrice() ?? 0;
			$optionPriceRub = PriceNormalizer::toRubInt($price);

			if ($optionPriceRub > 0) {
				$optionPrices[] = $optionPriceRub;
			}

			$selectedOptionsData[] = $this->createOptionData($assignment, $optionPriceRub);
			$optionsSnapshot[] = $this->createOptionSnapshot($assignment);
		}

		// Новая логика: если есть хоть одна опция с ценой, используем максимальную цену опции
		// Если опций с ценой нет, используем базовую цену товара
		if (!empty($optionPrices)) {
			$finalPrice = max($optionPrices);
			$item->setOptionsPriceModifier($finalPrice - $basePriceRub); // Для обратной совместимости
		} else {
			$finalPrice = $basePriceRub;
			$item->setOptionsPriceModifier(0);
		}

		$item->setUnitPrice($basePriceRub); // Сохраняем базовую цену для истории
		$item->setEffectiveUnitPrice($finalPrice);
		$item->setSelectedOptionsData($selectedOptionsData);
		$item->setOptionsSnapshot($optionsSnapshot);
		$item->setOptionsHash($optionsHash);
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



	public function updateQty(Cart $cart, int $itemId, int $qty): Cart
	{
		if ($qty <= 0) {
			return $this->removeItem($cart, $itemId);
		}

		return $this->executeWithLock($cart, function() use ($cart, $itemId, $qty) {
			$this->doUpdateQty($cart, $itemId, $qty);
		});
	}

	private function doUpdateQty(Cart $cart, int $itemId, int $qty): void
	{
		$item = $this->carts->findItemByIdForUpdate($cart, $itemId);
		if (!$item) {
			throw new CartItemNotFoundException('Cart item not found');
		}

		// Извлекаем optionAssignmentIds из товара
		$optionAssignmentIds = $this->getOptionAssignmentIdsFromItem($item);

		$this->inventory->assertAvailable($item->getProduct(), $qty, $optionAssignmentIds);
		$item->setQty($qty);

		// В LIVE режиме пересчитываем effectiveUnitPrice при изменении количества
		if ($cart->getPricingPolicy() === 'LIVE') {
			$newEffectivePrice = $this->livePrice->effectiveUnitPriceLive($item);
			$item->setEffectiveUnitPrice($newEffectivePrice);
		}

		// Пересчитываем rowTotal сразу после изменения количества
		$rowTotal = $item->getEffectiveUnitPrice() * $qty;
		$item->setRowTotal($rowTotal);
	}

	public function removeItem(Cart $cart, int $itemId): Cart
	{
		return $this->executeWithLock($cart, function() use ($cart, $itemId) {
			$this->doRemoveItem($cart, $itemId);
		});
	}

	private function doRemoveItem(Cart $cart, int $itemId): void
	{
		$item = $this->carts->findItemByIdForUpdate($cart, $itemId);
		if (!$item) {
			throw new CartItemNotFoundException('Cart item not found');
		}
		$this->em->remove($item);
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
				$existing = $this->carts->findItemForUpdate($target, $srcItem->getProduct(), $optionsHash);

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

	/**
	 * Выполняет addItem с возвратом информации об изменениях
	 */
	public function addItemWithChanges(Cart $cart, int $productId, int $qty, array $optionAssignmentIds = []): array
	{
		$beforeItems = $this->createItemsSnapshot($cart);

		$result = $this->addItem($cart, $productId, $qty, $optionAssignmentIds);

		$afterItems = $this->createItemsSnapshot($cart);
		$changes = $this->analyzeChanges($cart, $beforeItems, $afterItems);

		return [
			'cart' => $result,
			'changes' => $changes,
			'operation' => [
				'type' => 'add',
				'productId' => $productId,
				'qty' => $qty,
				'optionAssignmentIds' => $optionAssignmentIds,
			],
		];
	}

	/**
	 * Выполняет updateQty с возвратом информации об изменениях
	 */
	public function updateQtyWithChanges(Cart $cart, int $itemId, int $qty): array
	{
		$beforeItems = $this->createItemsSnapshot($cart);

		$result = $this->updateQty($cart, $itemId, $qty);

		$afterItems = $this->createItemsSnapshot($cart);
		$changes = $this->analyzeChanges($cart, $beforeItems, $afterItems);

		return [
			'cart' => $result,
			'changes' => $changes,
			'operation' => [
				'type' => 'update',
				'itemId' => $itemId,
				'qty' => $qty,
			],
		];
	}

	/**
	 * Выполняет removeItem с возвратом информации об изменениях
	 */
	public function removeItemWithChanges(Cart $cart, int $itemId): array
	{
		$beforeItems = $this->createItemsSnapshot($cart);

		$result = $this->removeItem($cart, $itemId);

		$afterItems = $this->createItemsSnapshot($cart);
		$changes = $this->analyzeChanges($cart, $beforeItems, $afterItems);

		return [
			'cart' => $result,
			'changes' => $changes,
			'operation' => [
				'type' => 'remove',
				'itemId' => $itemId,
			],
		];
	}

	/**
	 * Выполняет clearCart с возвратом информации об изменениях
	 */
	public function clearCartWithChanges(Cart $cart): array
	{
		$beforeItems = $this->createItemsSnapshot($cart);

		$result = $this->clearCart($cart);

		$afterItems = $this->createItemsSnapshot($cart);
		$changes = $this->analyzeChanges($cart, $beforeItems, $afterItems);

		return [
			'cart' => $result,
			'changes' => $changes,
			'operation' => [
				'type' => 'clear',
			],
		];
	}

	/**
	 * Выполняет батч операций с атомарностью
	 */
	public function executeBatch(Cart $cart, array $operations, bool $atomic = true): array
	{
		$beforeItems = $this->createItemsSnapshot($cart);
		$results = [];
		$allChanges = [];
		$hasErrors = false;

		if ($atomic) {
			// Атомарный режим: все или ничего
			return $this->executeWithLock($cart, function() use ($cart, $operations, $beforeItems) {
				return $this->doExecuteBatch($cart, $operations, $beforeItems, true);
			}) ?: ['cart' => $cart, 'changes' => [], 'results' => [], 'success' => false];
		} else {
			// Неатомарный режим: best-effort
			try {
				$batchResult = $this->executeWithLock($cart, function() use ($cart, $operations, $beforeItems) {
					return $this->doExecuteBatch($cart, $operations, $beforeItems, false);
				});
				return $batchResult ?: ['cart' => $cart, 'changes' => [], 'results' => [], 'success' => false];
			} catch (\Exception $e) {
				// В неатомарном режиме возвращаем частичный результат
				$afterItems = $this->createItemsSnapshot($cart);
				$changes = $this->analyzeChanges($cart, $beforeItems, $afterItems);

				return [
					'cart' => $cart,
					'changes' => $changes,
					'results' => array_map(fn($op) => [
						'index' => $op['index'] ?? 0,
						'status' => 'error',
						'error' => $e->getMessage(),
					], $operations),
					'success' => false,
				];
			}
		}
	}

	/**
	 * Выполняет батч операций внутри транзакции
	 */
	private function doExecuteBatch(Cart $cart, array $operations, array $beforeItems, bool $atomic): array
	{
		$results = [];
		$allChanges = [];

		foreach ($operations as $index => $operation) {
			try {
				$result = $this->executeSingleOperation($cart, $operation);
				$results[] = [
					'index' => $index,
					'status' => 'ok',
					...$result,
				];

				if (isset($result['changes'])) {
					$allChanges = array_merge($allChanges, $result['changes']);
				}

			} catch (\Exception $e) {
				if ($atomic) {
					// В атомарном режиме откатываем всю транзакцию
					throw $e;
				}

				$results[] = [
					'index' => $index,
					'status' => 'error',
					'error' => $e->getMessage(),
				];
			}
		}

		return [
			'cart' => $cart,
			'changes' => $allChanges,
			'results' => $results,
			'success' => !in_array('error', array_column($results, 'status')),
		];
	}

	/**
	 * Выполняет одну операцию из батча
	 */
	private function executeSingleOperation(Cart $cart, array $operation): array
	{
		return match ($operation['op']) {
			'add' => $this->doAddItemForBatch($cart, $operation),
			'update' => $this->doUpdateQtyForBatch($cart, $operation),
			'remove' => $this->doRemoveItemForBatch($cart, $operation),
			default => throw new \InvalidArgumentException("Unsupported operation: {$operation['op']}"),
		};
	}

	/**
	 * Добавление товара для батч-операции
	 */
	private function doAddItemForBatch(Cart $cart, array $operation): array
	{
		$beforeItems = $this->createItemsSnapshot($cart);

		$this->doAddItem(
			$cart,
			$operation['productId'],
			$operation['qty'] ?? 1,
			$operation['optionAssignmentIds'] ?? []
		);

		$afterItems = $this->createItemsSnapshot($cart);
		$changes = $this->analyzeChanges($cart, $beforeItems, $afterItems);

		// Находим добавленный/обновленный товар
		$addedItem = null;
		foreach ($changes as $change) {
			if ($change['type'] === 'changed') {
				$addedItem = $change['item'];
				break;
			}
		}

		return [
			'itemId' => $addedItem?->getId(),
			'changes' => $changes,
		];
	}

	/**
	 * Обновление количества для батч-операции
	 */
	private function doUpdateQtyForBatch(Cart $cart, array $operation): array
	{
		$beforeItems = $this->createItemsSnapshot($cart);

		$this->doUpdateQty($cart, $operation['itemId'], $operation['qty']);

		$afterItems = $this->createItemsSnapshot($cart);
		$changes = $this->analyzeChanges($cart, $beforeItems, $afterItems);

		return ['changes' => $changes];
	}

	/**
	 * Удаление товара для батч-операции
	 */
	private function doRemoveItemForBatch(Cart $cart, array $operation): array
	{
		$beforeItems = $this->createItemsSnapshot($cart);

		$this->doRemoveItem($cart, $operation['itemId']);

		$afterItems = $this->createItemsSnapshot($cart);
		$changes = $this->analyzeChanges($cart, $beforeItems, $afterItems);

		return ['changes' => $changes];
	}

	/**
	 * Создает снимок позиций корзины
	 */
	private function createItemsSnapshot(Cart $cart): array
	{
		$snapshot = [];
		foreach ($cart->getItems() as $item) {
			$snapshot[$item->getId()] = [
				'id' => $item->getId(),
				'qty' => $item->getQty(),
				'item' => $item,
			];
		}
		return $snapshot;
	}

	/**
	 * Анализирует изменения между двумя снимками
	 */
	private function analyzeChanges(Cart $cart, array $beforeItems, array $afterItems): array
	{
		$changes = [];

		// Анализ удаленных позиций
		foreach ($beforeItems as $itemId => $beforeItem) {
			if (!isset($afterItems[$itemId])) {
				$changes[] = [
					'type' => 'removed',
					'itemId' => $itemId,
				];
			}
		}

		// Анализ измененных позиций
		foreach ($afterItems as $itemId => $afterItem) {
			if (!isset($beforeItems[$itemId])) {
				// Новая позиция
				$changes[] = [
					'type' => 'changed',
					'item' => $afterItem['item'],
				];
			} elseif ($beforeItems[$itemId]['qty'] !== $afterItem['qty']) {
				// Измененное количество
				$changes[] = [
					'type' => 'changed',
					'item' => $afterItem['item'],
				];
			}
		}

		return $changes;
	}
}


