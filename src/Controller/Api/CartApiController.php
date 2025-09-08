<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\CartManager;
use App\Service\CartContext;
use App\Service\CartCalculator;
use App\Service\LivePriceCalculator;
use App\Service\CartDeltaBuilder;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Entity\User as AppUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, JsonResponse, Response};
use Symfony\Component\Routing\Attribute\Route;
use App\Service\CartLockException;
use App\Entity\Cart;
use App\Http\CartWriteGuard;
use App\Http\CartResponse;
use App\Http\CartEtags;
use App\Exception\CartItemNotFoundException;

#[Route('/api/cart')]
final class CartApiController extends AbstractController
{
	public function __construct(
		private CartManager $manager,
		private CartContext $cartContext,
		private CartRepository $carts,
		private ProductRepository $products,
		private \Doctrine\ORM\EntityManagerInterface $em,
		private LivePriceCalculator $livePrice,
		private CartCalculator $calculator,
		private CartWriteGuard $guard,
		private CartResponse $cartResponse,
		private CartEtags $etags,
		private CartDeltaBuilder $deltaBuilder
	) {}

	#[Route('', name: 'api_cart_get', methods: ['GET'])]
    public function getCart(Request $request): JsonResponse
	{
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		// Создаем response объект для получения cookies от CartContext
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

		$payload = $this->serializeCart($cart);
		$response->setData($payload);
		$response->setEtag($this->etags->make($cart));
		$response->headers->set('Cache-Control', 'private, no-cache, must-revalidate');

		if ($response->isNotModified($request)) return $response;
		return $response;
	}

	#[Route('/items', name: 'api_cart_add_item', methods: ['POST'])]
	public function addItem(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true) ?? [];

		$productId = (int)($data['productId'] ?? 0);
		$qty = (int)($data['qty'] ?? 1);

		// Добавляем обработку опций
		$optionAssignmentIds = [];
		if (isset($data['optionAssignmentIds']) && is_array($data['optionAssignmentIds'])) {
			$optionAssignmentIds = array_map('intval', $data['optionAssignmentIds']);
		}

		if ($productId < 1 || $qty < 1) {
			return $this->json(['error' => 'Invalid input'], 422);
		}

		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreateForWrite($userId, $response);

		// Проверяем предикаты записи
		try {
			$this->guard->assertPrecondition($request, $cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException $e) {
			return $this->createPreconditionFailedResponse($cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException $e) {
			return new JsonResponse(['error' => 'precondition_required', 'message' => $e->getMessage()], 428);
		}

		// Проверка Idempotency-Key
		$idempotencyKey = $request->headers->get('Idempotency-Key');
		if ($idempotencyKey) {
			$cacheKey = "cart_add_{$cart->getIdString()}_{$idempotencyKey}_{$productId}_{$qty}_" . md5(serialize($optionAssignmentIds));
			$cachedResult = $this->getCachedCartResult($cacheKey);

			if ($cachedResult) {
				$response->setData($cachedResult['payload']);
				$this->setResponseHeaders($response, $cachedResult['cart']);
				return $response;
			}
		}

		try {
			// Используем новый метод с отслеживанием изменений
			$result = $this->manager->addItemWithChanges($cart, $productId, $qty, $optionAssignmentIds);
			$cart = $result['cart'];
			$changes = $result['changes'];

		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (\DomainException $e) {
			return $this->json(['error' => $e->getMessage()], 422);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		}

		// Определяем режим ответа
		$responseMode = $this->deltaBuilder->determineResponseMode($request);

		$response->setStatusCode(201);
		$jsonResponse = $this->cartResponse->withCart($response, $cart, $request, $responseMode, $changes);

		// Кэшируем результат для Idempotency-Key
		if ($idempotencyKey && $responseMode === 'full') {
			$this->cacheCartResult($cacheKey, $cart, $jsonResponse->getData());
		}

		return $jsonResponse;
	}

	#[Route('/items/{itemId}', name: 'api_cart_update_qty', methods: ['PATCH'])]
	public function updateQty(int $itemId, Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true) ?? [];
		$qty = (int)($data['qty'] ?? 0);
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreateForWrite($userId, $response);

		// Проверяем предикаты записи
		try {
			$this->guard->assertPrecondition($request, $cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException $e) {
			return $this->createPreconditionFailedResponse($cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException $e) {
			return new JsonResponse(['error' => 'precondition_required', 'message' => $e->getMessage()], 428);
		}

		// Проверка Idempotency-Key
		$idempotencyKey = $request->headers->get('Idempotency-Key');
		if ($idempotencyKey) {
			$cacheKey = "cart_update_{$cart->getIdString()}_{$idempotencyKey}_{$itemId}_{$qty}";
			$cachedResult = $this->getCachedCartResult($cacheKey);

			if ($cachedResult) {
				$response->setData($cachedResult['payload']);
				$this->setResponseHeaders($response, $cachedResult['cart']);
				return $response;
			}
		}

		try {
			// Используем новый метод с отслеживанием изменений
			$result = $this->manager->updateQtyWithChanges($cart, $itemId, $qty);
			$cart = $result['cart'];
			$changes = $result['changes'];

		} catch (CartItemNotFoundException) {
			return new JsonResponse(['error' => 'cart_item_not_found'], 404);
		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (\DomainException $e) {
			return $this->json(['error' => $e->getMessage()], 422);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		}

		// Определяем режим ответа
		$responseMode = $this->deltaBuilder->determineResponseMode($request);

		$jsonResponse = $this->cartResponse->withCart($response, $cart, $request, $responseMode, $changes);

		// Кэшируем результат для Idempotency-Key
		if ($idempotencyKey && $responseMode === 'full') {
			$this->cacheCartResult($cacheKey, $cart, $jsonResponse->getData());
		}

		return $jsonResponse;
	}

	#[Route('/items/{itemId}', name: 'api_cart_remove_item', methods: ['DELETE'])]
	public function removeItem(int $itemId, Request $request): JsonResponse
	{
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreateForWrite($userId, $response);

		// Проверяем предикаты записи
		try {
			$this->guard->assertPrecondition($request, $cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException $e) {
			return $this->createPreconditionFailedResponse($cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException $e) {
			return new JsonResponse(['error' => 'precondition_required', 'message' => $e->getMessage()], 428);
		}

		// Проверка Idempotency-Key
		$idempotencyKey = $request->headers->get('Idempotency-Key');
		if ($idempotencyKey) {
			$cacheKey = "cart_remove_{$cart->getIdString()}_{$idempotencyKey}_{$itemId}";
			$cachedResult = $this->getCachedCartResult($cacheKey);

			if ($cachedResult) {
				$response->setData($cachedResult['payload']);
				$this->setResponseHeaders($response, $cachedResult['cart']);
				return $response;
			}
		}

		try {
			// Используем новый метод с отслеживанием изменений
			$result = $this->manager->removeItemWithChanges($cart, $itemId);
			$cart = $result['cart'];
			$changes = $result['changes'];

		} catch (CartItemNotFoundException) {
			return new JsonResponse(['error' => 'cart_item_not_found'], 404);
		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		}

		// Определяем режим ответа
		$responseMode = $this->deltaBuilder->determineResponseMode($request);

		$jsonResponse = $this->cartResponse->withCart($response, $cart, $request, $responseMode, $changes);

		// Кэшируем результат для Idempotency-Key
		if ($idempotencyKey && $responseMode === 'full') {
			$this->cacheCartResult($cacheKey, $cart, $jsonResponse->getData());
		}

		return $jsonResponse;
	}

	#[Route('', name: 'api_cart_clear', methods: ['DELETE'])]
	public function clearCart(Request $request): JsonResponse
	{
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreateForWrite($userId, $response);

		// Проверяем предикаты записи
		try {
			$this->guard->assertPrecondition($request, $cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException $e) {
			return $this->createPreconditionFailedResponse($cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException $e) {
			return new JsonResponse(['error' => 'precondition_required', 'message' => $e->getMessage()], 428);
		}

		try {
			// Используем новый метод с отслеживанием изменений
			$result = $this->manager->clearCartWithChanges($cart);
			$cart = $result['cart'];
			$changes = $result['changes'];

		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		}

		// Определяем режим ответа
		$responseMode = $this->deltaBuilder->determineResponseMode($request);

		// Для clear всегда возвращаем 204, независимо от режима
		$response->setStatusCode(204);
		return $this->cartResponse->withCart($response, $cart, $request, $responseMode, $changes);
	}

	#[Route('', name: 'api_cart_update_pricing_policy', methods: ['PATCH'])]
	public function updatePricingPolicy(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true) ?? [];

		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreateForWrite($userId, $response);

		// Проверяем предикаты записи
		try {
			$this->guard->assertPrecondition($request, $cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException $e) {
			return $this->createPreconditionFailedResponse($cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException $e) {
			return new JsonResponse(['error' => 'precondition_required', 'message' => $e->getMessage()], 428);
		}

		$policy = $data['pricingPolicy'] ?? null;
		if (!in_array($policy, ['SNAPSHOT', 'LIVE'], true)) {
			return $this->json(['error' => 'Invalid pricing policy. Allowed: SNAPSHOT, LIVE'], 422);
		}

		try {
			$cart->setPricingPolicy($policy);
			$this->em->flush();
		} catch (\InvalidArgumentException $e) {
			return $this->json(['error' => $e->getMessage()], 422);
		}

		$payload = $this->serializeCart($cart);
		return $this->cartResponse->withCart($response, $cart, $payload);
	}

	#[Route('/reprice', name: 'api_cart_reprice', methods: ['POST'])]
	public function repriceCart(Request $request): JsonResponse
	{
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreateForWrite($userId, $response);

		// Проверяем предикаты записи
		try {
			$this->guard->assertPrecondition($request, $cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException $e) {
			return $this->createPreconditionFailedResponse($cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException $e) {
			return new JsonResponse(['error' => 'precondition_required', 'message' => $e->getMessage()], 428);
		}

		// Обновляем снепшот всех позиций актуальными live-ценами
		foreach ($cart->getItems() as $item) {
			$livePrice = $this->livePrice->effectiveUnitPriceLive($item);

			// Перезаписываем снепшот новыми значениями
			$item->setEffectiveUnitPrice($livePrice);
			$item->setPricedAt(new \DateTimeImmutable());
			// unitPrice и optionsPriceModifier остаются неизменными для совместимости
		}

		$this->em->flush();

		// Пересчитываем корзину
		$this->calculator->recalculate($cart);

		$payload = $this->serializeCart($cart);
		return $this->cartResponse->withCart($response, $cart, $payload);
	}

	#[Route('/products/{productId}/options', name: 'api_product_options', methods: ['GET'])]
	public function getProductOptions(int $productId): JsonResponse
	{
		$product = $this->products->find($productId);
		if (!$product) {
			return $this->json(['error' => 'Product not found'], 404);
		}
		
		$options = [];
		foreach ($product->getOptionAssignments() as $assignment) {
			$optionCode = $assignment->getOption()->getCode();
			if (!isset($options[$optionCode])) {
				$options[$optionCode] = [
					'code' => $optionCode,
					'name' => $assignment->getOption()->getName(),
					'values' => []
				];
			}
			
			$options[$optionCode]['values'][] = [
				'assignmentId' => $assignment->getId(), // Это ID для отправки в корзину
				'valueCode' => $assignment->getValue()->getCode(),
				'valueName' => $assignment->getValue()->getValue(),
				'price' => $assignment->getPrice(),
				'salePrice' => $assignment->getSalePrice(),
				'sku' => $assignment->getSku(),
				'quantity' => $assignment->getQuantity(),
				'attributes' => $assignment->getAttributes(),
			];
		}
		
		return $this->json(array_values($options));
	}

	private function serializeCart($cart): array
	{
		$policy = $cart->getPricingPolicy();

		return [
			'id' => $cart->getIdString(),
			'currency' => $cart->getCurrency(),
			'pricingPolicy' => $policy,
			'subtotal' => $cart->getSubtotal(),
			'discountTotal' => $cart->getDiscountTotal(),
			'total' => $cart->getTotal(),
			'shipping' => [
				'method' => $cart->getShippingMethod(),
				'cost' => $cart->getShippingCost(),
				'city' => $cart->getShipToCity(),
				'data' => $cart->getShippingData(),
			],
			'items' => array_map(function($i) use ($policy) {
				$data = [
					'id' => $i->getId(),
					'productId' => $i->getProduct()->getId(),
					'name' => $i->getProductName(),
					'unitPrice' => $i->getUnitPrice(),
					'optionsPriceModifier' => $i->getOptionsPriceModifier(),
					'effectiveUnitPrice' => $i->getEffectiveUnitPrice(),
					'qty' => $i->getQty(),
					'rowTotal' => $i->getRowTotal(),
					'pricedAt' => $i->getPricedAt()->format(DATE_ATOM),
					'selectedOptions' => $i->getOptionsSnapshot() ?? $i->getSelectedOptionsData() ?? [],
					'optionsHash' => $i->getOptionsHash(),
				];

				// Добавляем live-данные только в LIVE режиме
				if ($policy === 'LIVE') {
					$liveEffectiveUnitPrice = $this->livePrice->effectiveUnitPriceLive($i);
					$liveRowTotal = $liveEffectiveUnitPrice * $i->getQty();

					$data['currentEffectiveUnitPrice'] = $liveEffectiveUnitPrice;
					$data['currentRowTotal'] = $liveRowTotal;
					$data['priceChanged'] = $liveEffectiveUnitPrice !== $i->getEffectiveUnitPrice();
				}

				return $data;
			}, $cart->getItems()->toArray()),
		];
	}

	/**
	 * Создает ответ при занятости корзины (423 Locked)
	 */
	private function createBusyResponse(CartLockException $e): JsonResponse
	{
		return new JsonResponse([
			'error' => 'cart_busy',
			'message' => 'Cart is busy, try again',
			'retryAfterMs' => $e->getRetryAfterMs(),
		], Response::HTTP_LOCKED);
	}

	/**
	 * Создает ответ при конфликте версий (409 Conflict)
	 */
	private function createConflictResponse(): JsonResponse
	{
		return new JsonResponse([
			'error' => 'version_conflict',
			'message' => 'Cart was modified by another request, please retry',
		], Response::HTTP_CONFLICT);
	}

	/**
	 * Создает ответ при несоответствии версии (412 Precondition Failed)
	 */
	private function createPreconditionFailedResponse(Cart $cart): JsonResponse
	{
		return new JsonResponse([
			'error' => 'precondition_failed',
			'message' => 'Cart version mismatch',
			'currentVersion' => $cart->getVersion(),
			'cart' => $this->serializeCart($cart),
		], Response::HTTP_PRECONDITION_FAILED);
	}

	#[Route('/batch', name: 'api_cart_batch', methods: ['POST'])]
	public function batch(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true) ?? [];

		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreateForWrite($userId, $response);

		// Проверяем предикаты записи
		try {
			$this->guard->assertPrecondition($request, $cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException $e) {
			return $this->createPreconditionFailedResponse($cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException $e) {
			return new JsonResponse(['error' => 'precondition_required', 'message' => $e->getMessage()], 428);
		}

		// Валидация входных данных
		$operations = $data['operations'] ?? [];
		$atomic = $data['atomic'] ?? true;
		$idempotencyKey = $request->headers->get('Idempotency-Key');

		if (empty($operations)) {
			return new JsonResponse(['error' => 'No operations provided'], 400);
		}

		if (!is_array($operations)) {
			return new JsonResponse(['error' => 'Operations must be an array'], 400);
		}

		// Валидация операций
		foreach ($operations as $index => $operation) {
			if (!is_array($operation) || !isset($operation['op'])) {
				return new JsonResponse(['error' => "Invalid operation at index {$index}"], 400);
			}

			if (!in_array($operation['op'], ['add', 'update', 'remove'], true)) {
				return new JsonResponse(['error' => "Unsupported operation '{$operation['op']}' at index {$index}"], 400);
			}
		}

		// Проверка Idempotency-Key
		if ($idempotencyKey) {
			$cacheKey = "cart_batch_{$cart->getIdString()}_{$idempotencyKey}";
			$cachedResult = $this->getCachedBatchResult($cacheKey);

			if ($cachedResult) {
				$response->setData($cachedResult);
				$this->cartResponse->setResponseHeaders($response, $cart);
				return $response;
			}
		}

		try {
			$batchResult = $this->manager->executeBatch($cart, $operations, $atomic);

			// Кэшируем результат для Idempotency-Key
			if ($idempotencyKey && $batchResult['success']) {
				$this->cacheBatchResult($cacheKey, $batchResult);
			}

			if (!$batchResult['success']) {
				// Частичный успех или полный провал в атомарном режиме
				return $this->cartResponse->withBatchError(
					$response,
					$atomic ? 'Batch operation failed' : 'Some operations failed',
					$batchResult['results'],
					$atomic ? 400 : 207 // 207 Multi-Status для частичного успеха
				);
			}

			// Успешное выполнение
			return $this->cartResponse->withBatchResult(
				$response,
				$batchResult['cart'],
				$batchResult['results'],
				$batchResult['changes']
			);

		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (\DomainException $e) {
			return new JsonResponse(['error' => $e->getMessage()], 422);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		} catch (\Exception $e) {
			return new JsonResponse(['error' => 'Batch operation failed: ' . $e->getMessage()], 500);
		}
	}

	/**
	 * Получает кэшированный результат батч-операции
	 */
	private function getCachedBatchResult(string $cacheKey): ?array
	{
		// Простая in-memory реализация, в продакшене использовать Redis/APCu
		static $cache = [];
		return $cache[$cacheKey] ?? null;
	}

	/**
	 * Кэширует результат батч-операции
	 */
	private function cacheBatchResult(string $cacheKey, array $result, int $ttl = 3600): void
	{
		// Простая in-memory реализация, в продакшене использовать Redis/APCu
		static $cache = [];
		$cache[$cacheKey] = $result;

		// Очистка старых записей (простая реализация)
		if (count($cache) > 1000) {
			$cache = array_slice($cache, -500, null, true);
		}
	}

	/**
	 * Получает кэшированный результат операции корзины
	 */
	private function getCachedCartResult(string $cacheKey): ?array
	{
		// Простая in-memory реализация, в продакшене использовать Redis/APCu
		static $cache = [];
		return $cache[$cacheKey] ?? null;
	}

	/**
	 * Кэширует результат операции корзины
	 */
	private function cacheCartResult(string $cacheKey, $cart, $payload, int $ttl = 3600): void
	{
		// Простая in-memory реализация, в продакшене использовать Redis/APCu
		static $cache = [];
		$cache[$cacheKey] = [
			'cart' => $cart,
			'payload' => $payload,
			'expires' => time() + $ttl,
		];

		// Очистка старых записей
		if (count($cache) > 1000) {
			$cache = array_filter($cache, fn($item) => $item['expires'] > time());
			if (count($cache) > 500) {
				// Сортируем по времени истечения и оставляем только самые свежие
				uasort($cache, fn($a, $b) => $b['expires'] <=> $a['expires']);
				$cache = array_slice($cache, 0, 500, true);
			}
		}
	}

	/**
	 * Устанавливает заголовки ответа для кэшированного результата
	 */
	private function setResponseHeaders(JsonResponse $response, $cart): void
	{
		$this->cartResponse->setResponseHeaders($response, $cart);
	}
}


