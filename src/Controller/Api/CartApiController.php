<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\CartManager;
use App\Service\CartContext;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Entity\User as AppUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, JsonResponse};
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cart')]
final class CartApiController extends AbstractController
{
	public function __construct(
		private CartManager $manager,
		private CartContext $cartContext,
		private CartRepository $carts,
		private ProductRepository $products,
		private \Doctrine\ORM\EntityManagerInterface $em
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

		try {
			// Передаем опции в метод addItem
			$this->manager->addItem($cart, $productId, $qty, $optionAssignmentIds);
		} catch (\DomainException $e) {
			return $this->json(['error' => $e->getMessage()], 422);
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
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		// Создаем объект JsonResponse
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

		try {
			$this->manager->updateQty($cart, $itemId, $qty);
		} catch (\DomainException $e) {
			return $this->json(['error' => $e->getMessage()], 422);
		}

		$response->setData($this->serializeCart($cart));
		return $response;
	}

	#[Route('/items/{itemId}', name: 'api_cart_remove_item', methods: ['DELETE'])]
	public function removeItem(int $itemId): JsonResponse
	{
		error_log("CartApiController: Starting removal of item ID {$itemId}");

		// Пробуем найти товар напрямую в базе данных
		$item = $this->em->getRepository(\App\Entity\CartItem::class)->find($itemId);

		if (!$item) {
			error_log("CartApiController: Item {$itemId} not found");
			return new JsonResponse(null, 204);
		}

		$cart = $item->getCart();
		error_log("CartApiController: Found item in cart " . ($cart ? $cart->getIdString() : 'null'));

		if (!$cart) {
			error_log("CartApiController: Item has no cart");
			return new JsonResponse(null, 204);
		}

		// Удаляем товар напрямую
		$this->em->remove($item);
		$this->em->flush();

		error_log("CartApiController: Item {$itemId} removed successfully");

		// Возвращаем обновленные данные корзины
		return new JsonResponse($this->serializeCart($cart), 200);
	}

	#[Route('', name: 'api_cart_clear', methods: ['DELETE'])]
	public function clearCart(): JsonResponse
	{
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		// Создаем объект JsonResponse
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

		$this->manager->clearCart($cart);
		$response->setData(null);
		$response->setStatusCode(204);
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
		return [
			'id' => $cart->getIdString(), // Используем строковое представление ULID
			'currency' => $cart->getCurrency(),
			'subtotal' => $cart->getSubtotal(),
            'discountTotal' => $cart->getDiscountTotal(),
			'total' => $cart->getTotal(),
            'shipping' => [
                'method' => $cart->getShippingMethod(),
                'cost' => $cart->getShippingCost(),
                'city' => $cart->getShipToCity(),
                'data' => $cart->getShippingData(),
            ],
			'items' => array_map(fn($i) => [
				'id' => $i->getId(), // CartItem ID тоже должен быть строкой
				'productId' => $i->getProduct()->getId(),
				'name' => $i->getProductName(),
				'unitPrice' => $i->getUnitPrice(),
				'qty' => $i->getQty(),
				'rowTotal' => $i->getRowTotal(),
				// Добавляем информацию об опциях
				'optionsPriceModifier' => $i->getOptionsPriceModifier(),
				'effectiveUnitPrice' => $i->getEffectiveUnitPrice(),
				'selectedOptions' => $i->getOptionsSnapshot() ?? $i->getSelectedOptionsData() ?? [],
				'optionsHash' => $i->getOptionsHash(),
			], $cart->getItems()->toArray()),
		];
	}
}


