<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Entity\User as AppUser;
use App\Entity\Order;
use App\Entity\OrderCustomer;
use App\Entity\OrderProducts;
use App\Entity\OrderProductOptions;
use App\Entity\OrderDelivery;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\CartManager;
use App\Service\CheckoutContext;
use App\Service\DeliveryContext;
use App\Service\Delivery\DeliveryService;
use App\Service\Delivery\Provider\DeliveryProviderRegistry;
use App\Exception\InvalidDeliveryDataException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Fias;
use App\Entity\PvzPoints;

/**
 * AI-META v1
 * role: Страница и API оформления заказа; перенос данных из Cart в Order
 * module: Order
 * dependsOn:
 *   - App\Service\CartManager
 *   - App\Service\CheckoutContext
 *   - App\Service\Delivery\DeliveryContext
 *   - App\Service\Delivery\DeliveryService
 *   - App\Service\Delivery\Provider\DeliveryProviderRegistry
 *   - App\Repository\OrderRepository
 *   - Doctrine\ORM\EntityManagerInterface
 * invariants:
 *   - Создание Order/связанных сущностей выполняется в одной транзакции Doctrine
 *   - После успешного оформления корзина помечается истёкшей (мягкое закрытие)
 *   - Валидация данных доставки провайдером перед сохранением
 * transaction: em
 * routes:
 *   - GET /checkout checkout_page
 *   - POST /checkout checkout_submit
 *   - GET /checkout/success/{orderId} checkout_success
 * lastUpdated: 2025-09-15
 */
final class CheckoutController extends AbstractController
{
	public function __construct(
		private readonly DeliveryProviderRegistry $deliveryProviderRegistry,
		private readonly DeliveryContext $deliveryContext
	) {}

	#[Route('/checkout', name: 'checkout_page', methods: ['GET'])]
	public function index(CartManager $cartManager, DeliveryService $deliveryService): Response
	{
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;
		$cart = $cartManager->getOrCreateCurrent($userId);

		// Расчёт доставки и контекста, как на странице корзины
		$deliveryResult = $deliveryService->calculateForCart($cart);
		$ctx = $this->deliveryContext->get();

		return $this->render('catalog/checkout/index.html.twig', [
			'cart' => $cart,
			'delivery' => $deliveryResult,
			'deliveryContext' => $ctx,
		]);
	}

	/**
	 * AI-META v1
	 * role: Транзакционное оформление заказа (создание Order из Cart)
	 * module: Order
	 * dependsOn:
	 *   - Doctrine\ORM\EntityManagerInterface
	 *   - App\Repository\OrderRepository
	 *   - App\Service\Delivery\DeliveryService
	 * invariants:
	 *   - Вся операция выполняется в одной транзакции Doctrine
	 *   - Корзина помечается истёкшей после успешного оформления
	 * transaction: em
	 * routes:
	 *   - POST /checkout checkout_submit
	 * lastUpdated: 2025-09-15
	 */
	#[Route('/checkout', name: 'checkout_submit', methods: ['POST'])]
	public function submit(
		Request $request,
		CartManager $cartManager,
		CheckoutContext $checkout,
		OrderRepository $orders,
		EntityManagerInterface $em,
		DeliveryService $deliveryService,
		DeliveryContext $deliveryContext
	): Response {
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;
		$cart = $cartManager->getOrCreateForWrite($userId);
		if ($cart->getItems()->count() === 0) {
			// Для фронта возвращаем JSON с 409 и ссылкой на корзину, чтобы избежать HTML redirect
			return $this->json([
				'error' => 'Cart is empty',
				'redirectUrl' => $this->generateUrl('cart_page'),
			], 409);
		}

		$payload = json_decode($request->getContent() ?: '[]', true);
		$name = trim((string)($payload['firstName'] ?? ''));
		$phone = trim((string)($payload['phone'] ?? ''));
		$email = trim((string)($payload['email'] ?? ''));
		$comment = trim((string)($payload['comment'] ?? ''));
		$paymentMethod = isset($payload['paymentMethod']) ? trim((string)$payload['paymentMethod']) : null;

		// Санитизация: убираем управляющие/невидимые символы и проверяем длины полей
		$sanitize = static function (?string $s): string {
			$s = (string)$s;
			$s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
			return trim($s);
		};
		$name = $sanitize($name);
		$phone = $sanitize($phone);
		$email = $sanitize($email);
		$comment = $sanitize($comment);

		if (mb_strlen($name) > 255 || mb_strlen($phone) > 255 || mb_strlen($email) > 255 || mb_strlen($comment) > 1000) {
			return $this->json(['error' => 'Поля слишком длинные'], 400);
		}

		// Лёгкая серверная валидация телефона (РФ/Generic)
		$digits = preg_replace('/\D+/', '', $phone) ?? '';
		$validPhone = (strlen($digits) >= 10 && strlen($digits) <= 15);
		if ($name === '' || !$validPhone || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
			return $this->json(['error' => 'Проверьте корректность данных'], 400);
		}

		// Сохраняем черновик в сессию
		$checkout->setCustomer([
			'name' => $name,
			'phone' => $phone,
			'email' => $email ?: null,
			'ip' => $request->getClientIp(),
			'userAgent' => $request->headers->get('User-Agent'),
			'comment' => $comment ?: null,
		]);
		$checkout->setComment($comment ?: null);
		$checkout->setPaymentMethod($paymentMethod ?: null);

		// Оформление заказа в транзакции: создание Order/связанных сущностей, закрытие корзины, очистка CheckoutContext
		$createdOrder = null;
		try {
			$em->wrapInTransaction(function() use (
				$em,
				$orders,
				$deliveryService,
				$deliveryContext,
				$cart,
				$name,
				$phone,
				$email,
				$comment,
				$request,
				$payload,
				&$createdOrder
			) {
				$order = new Order();
				$order->setOrderId($orders->getNextOrderId());
				$order->setComment($comment ?: null);
				$order->setTotal($cart->getTotal());

				$customer = new OrderCustomer();
				$customer->setName($name);
				$customer->setPhone($phone);
				$customer->setEmail($email ?: null);
				$customer->setIp($request->getClientIp());
				$customer->setUserAgent($request->headers->get('User-Agent'));
				$order->setCustomer($customer);
				$customer->setOrders($order);

				$ctx = $deliveryContext->get();
				$method = $ctx['methodCode'] ?? 'pvz';
				$cityName = $ctx['cityName'] ?? null;

				$calc = $deliveryService->calculateForCart($cart);

				$orderDelivery = new OrderDelivery();
				$orderDelivery->setType($method);
				if ($cityName) {
					$orderDelivery->setCity($cityName);
				}
				if ($calc->cost !== null) {
					$orderDelivery->setCost($calc->cost);
				}
				if ($calc->isFree) {
					$orderDelivery->setIsFree(true);
				}
				if ($calc->requiresManagerCalculation) {
					$orderDelivery->setIsCustomCalculate(true);
				}
				if (!empty($calc->traceData)) {
					$source = is_array($calc->traceData) && isset($calc->traceData['source']) ? (string)$calc->traceData['source'] : null;
					if ($source !== null) {
						$orderDelivery->setPricingSource($source);
					}
					$orderDelivery->setPricingTrace($calc->traceData);
				}

				if ($method === 'pvz' && !empty($ctx['pickupPointId'])) {
					$pvzCode = (string)$ctx['pickupPointId'];
					$point = $em->getRepository(PvzPoints::class)->findOneBy(['code' => $pvzCode]);
					if ($point && strcasecmp((string)$point->getCity(), (string)$cityName) === 0) {
						$orderDelivery->setPvz($pvzCode);
						$orderDelivery->setPvzCode($pvzCode);
					} else {
						$orderDelivery->setIsCustomCalculate(true);
					}
				}

				if ($method === 'courier' && !empty($ctx['address'])) {
					$orderDelivery->setAddress(substr(trim((string)$ctx['address']), 0, 255));
				}

				$cityId = isset($payload['cityId']) ? (int)$payload['cityId'] : null;
				if ($cityId && $cityId > 0) {
					$cityRef = $em->getReference(Fias::class, $cityId);
					$orderDelivery->setCityFias($cityRef);
				}

				$deliveryProvider = $this->deliveryProviderRegistry->get($method);
				if ($deliveryProvider) {
					$deliveryProvider->validate($orderDelivery);
				}

				$order->setDelivery($orderDelivery);
				$em->persist($orderDelivery);

				foreach ($cart->getItems() as $it) {
					$op = new OrderProducts();
					$op->setProductId($it->getProduct()->getId());
					$op->setProductName($it->getProductName());
					$op->setPrice($it->getUnitPrice());
					$op->setQuantity($it->getQty());
					$order->addProduct($op);
					$em->persist($op);

					// Перенос опций товара в OrderProductOptions
					$selectedOptions = $it->getSelectedOptionsData() ?: [];
					foreach ($selectedOptions as $opt) {
						$opo = new OrderProductOptions();
						$opo->setProduct($op);
						$opo->setProductId($it->getProduct()->getId());
						$opo->setOptionName(isset($opt['optionName']) ? (string)$opt['optionName'] : null);
						$opo->setValue([
							'optionCode' => $opt['optionCode'] ?? null,
							'valueCode' => $opt['valueCode'] ?? null,
							'valueName' => $opt['valueName'] ?? null,
							'sku' => $opt['sku'] ?? null,
						]);
						$opo->setPrice(isset($opt['price']) ? (int)$opt['price'] : null);
						$em->persist($opo);
					}
				}

				$em->persist($customer);
				$em->persist($order);

				// Закрываем корзину: помечаем истекшей
				$cart->setExpiresAt(new \DateTimeImmutable('-1 second'));

				// Фиксируем изменения
				$em->flush();

				$createdOrder = $order;
			});
		} catch (InvalidDeliveryDataException $e) {
			return $this->json(['error' => $e->getMessage()], 400);
		}

		// Очистка checkout-контекста после успешной транзакции
		$checkout->clear();

		return $this->json([
			'id' => $createdOrder?->getId(),
			'orderId' => $createdOrder?->getOrderId(),
			'redirectUrl' => $createdOrder ? $this->generateUrl('checkout_success', ['orderId' => $createdOrder->getOrderId()]) : null,
		]);
	}

	#[Route('/checkout/success/{orderId}', name: 'checkout_success', methods: ['GET'])]
	public function success(int $orderId): Response
	{
		return $this->render('catalog/checkout/success.html.twig', [
			'orderId' => $orderId,
		]);
	}
}



