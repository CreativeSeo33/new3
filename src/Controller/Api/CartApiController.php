<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\CartManager;
use App\Service\CartContext;
use App\Service\CartCalculator;
use App\Service\LivePriceCalculator;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Entity\User as AppUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, JsonResponse, Response};
use Symfony\Component\Routing\Attribute\Route;
use App\Service\CartLockException;
use App\Entity\Cart;

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
		private CartCalculator $calculator
	) {}

	#[Route('', name: 'api_cart_get', methods: ['GET'])]
    public function getCart(Request $request): JsonResponse
	{
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		// Создаем объект JsonResponse
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

		$payload = $this->serializeCart($cart);
		$etag = md5($cart->getUpdatedAt()->getTimestamp().':'.$cart->getVersion());
		$response->setData($payload);
		$response->setEtag($etag);

		if ($response->isNotModified($request)) return $response;
		return $response;
	}

	#[Route('/items', name: 'api_cart_add_item', methods: ['POST'])]
	public function addItem(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true) ?? [];

		$productId = (int)($data['productId'] ?? 0);
		$qty = (int)($data['qty'] ?? 1);
		$clientVersion = $data['version'] ?? null;

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

		// Создаем объект JsonResponse
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

		// Проверяем версию корзины для условного доступа
		if ($clientVersion !== null && $cart->getVersion() !== $clientVersion) {
			return $this->createPreconditionFailedResponse($cart);
		}

		try {
			// Передаем опции в метод addItem
			$this->manager->addItem($cart, $productId, $qty, $optionAssignmentIds);
		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (\DomainException $e) {
			return $this->json(['error' => $e->getMessage()], 422);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		}

		$response->setData($this->serializeCart($cart));
		$response->setStatusCode(201);
		return $response;
	}

	#[Route('/items/{itemId}', name: 'api_cart_update_qty', methods: ['PATCH'])]
	public function updateQty(int $itemId, Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true) ?? [];
		$qty = (int)($data['qty'] ?? 0);
		$clientVersion = $data['version'] ?? null;
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		// Создаем объект JsonResponse
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

		// Проверяем версию корзины для условного доступа
		if ($clientVersion !== null && $cart->getVersion() !== $clientVersion) {
			return $this->createPreconditionFailedResponse($cart);
		}

		try {
			$this->manager->updateQty($cart, $itemId, $qty);
		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (\DomainException $e) {
			return $this->json(['error' => $e->getMessage()], 422);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		}

		$response->setData($this->serializeCart($cart));
		return $response;
	}

	#[Route('/items/{itemId}', name: 'api_cart_remove_item', methods: ['DELETE'])]
	public function removeItem(int $itemId, Request $request): JsonResponse
	{
		$clientVersion = $request->query->get('version');
		if ($clientVersion !== null) {
			$clientVersion = (int)$clientVersion;
		}

		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		// Создаем объект JsonResponse
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

		// Проверяем версию корзины для условного доступа
		if ($clientVersion !== null && $cart->getVersion() !== $clientVersion) {
			return $this->createPreconditionFailedResponse($cart);
		}

		try {
			$result = $this->manager->removeItem($cart, $itemId);
			if (!$result) {
				return new JsonResponse(null, 204);
			}
		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		}

		$response->setData($this->serializeCart($cart));
		return $response;
	}

	#[Route('', name: 'api_cart_clear', methods: ['DELETE'])]
	public function clearCart(Request $request): JsonResponse
	{
		$clientVersion = $request->query->get('version');
		if ($clientVersion !== null) {
			$clientVersion = (int)$clientVersion;
		}

		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		// Создаем объект JsonResponse
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

		// Проверяем версию корзины для условного доступа
		if ($clientVersion !== null && $cart->getVersion() !== $clientVersion) {
			return $this->createPreconditionFailedResponse($cart);
		}

		try {
			$this->manager->clearCart($cart);
		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		}

		$response->setData(null);
		$response->setStatusCode(204);
		return $response;
	}

	#[Route('', name: 'api_cart_update_pricing_policy', methods: ['PATCH'])]
	public function updatePricingPolicy(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true) ?? [];

		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		// Создаем объект JsonResponse
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

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

		$response->setData($this->serializeCart($cart));
		return $response;
	}

	#[Route('/reprice', name: 'api_cart_reprice', methods: ['POST'])]
	public function repriceCart(): JsonResponse
	{
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		// Создаем объект JsonResponse
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

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

		$response->setData($this->serializeCart($cart));
		return $response;
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
}


