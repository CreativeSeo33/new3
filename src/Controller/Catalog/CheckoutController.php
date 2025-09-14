<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Entity\User as AppUser;
use App\Entity\Order;
use App\Entity\OrderCustomer;
use App\Entity\OrderProducts;
use App\Entity\OrderDelivery;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\CartManager;
use App\Service\CheckoutContext;
use App\Service\DeliveryContext;
use App\Service\Delivery\Provider\DeliveryProviderRegistry;
use App\Exception\InvalidDeliveryDataException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Fias;

final class CheckoutController extends AbstractController
{
	public function __construct(
		private readonly DeliveryProviderRegistry $deliveryProviderRegistry,
		private readonly DeliveryContext $deliveryContext
	) {}

	#[Route('/checkout', name: 'checkout_page', methods: ['GET'])]
	public function index(CartManager $cartManager): Response
	{
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;
		$cart = $cartManager->getOrCreateCurrent($userId);

		return $this->render('catalog/checkout/index.html.twig', [
			'cart' => $cart,
		]);
	}

	#[Route('/checkout', name: 'checkout_submit', methods: ['POST'])]
	public function submit(
		Request $request,
		CartManager $cartManager,
		CheckoutContext $checkout,
		OrderRepository $orders,
		EntityManagerInterface $em
	): Response {
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;
		$cart = $cartManager->getOrCreateCurrent($userId);
		if ($cart->getItems()->count() === 0) {
			return $this->redirectToRoute('cart_page');
		}

		$payload = json_decode($request->getContent() ?: '[]', true);
		$name = trim((string)($payload['firstName'] ?? ''));
		$phone = trim((string)($payload['phone'] ?? ''));
		$email = trim((string)($payload['email'] ?? ''));
		$comment = trim((string)($payload['comment'] ?? ''));
		$paymentMethod = isset($payload['paymentMethod']) ? trim((string)$payload['paymentMethod']) : null;

		if ($name === '' || $phone === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
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
		$customer->setOrders($order); // обратная связь

		// Создание и валидация доставки
		$deliveryContext = $this->deliveryContext->get();
		$methodCode = $deliveryContext['methodCode'] ?? null;

		if ($methodCode) {
			$deliveryProvider = $this->deliveryProviderRegistry->get($methodCode);
			if (!$deliveryProvider) {
				return $this->json(['error' => 'Неверный метод доставки'], 400);
			}

			$orderDelivery = new OrderDelivery();
			$orderDelivery->setType($methodCode);
			$orderDelivery->setCity($deliveryContext['cityName'] ?? null);
			$orderDelivery->setAddress($deliveryContext['address'] ?? null);
			$orderDelivery->setPvzCode($deliveryContext['pickupPointId'] ?? null);
			$orderDelivery->setCost($cart->getShippingCost());

			// Если фронт прислал cityId, установим связь с FIAS
			$cityId = isset($payload['cityId']) ? (int)$payload['cityId'] : null;
			if ($cityId && $cityId > 0) {
				$cityRef = $em->getReference(Fias::class, $cityId);
				$orderDelivery->setCityFias($cityRef);
			}

			try {
				$deliveryProvider->validate($orderDelivery);
			} catch (InvalidDeliveryDataException $e) {
				return $this->json(['error' => $e->getMessage()], 400);
			}

			$order->setDelivery($orderDelivery);
			$em->persist($orderDelivery);
		}

		foreach ($cart->getItems() as $it) {
			$op = new OrderProducts();
			$op->setProductId($it->getProduct()->getId());
			$op->setProductName($it->getProductName());
			$op->setPrice($it->getUnitPrice());
			$op->setQuantity($it->getQty());
			$order->addProduct($op);
			$em->persist($op);
		}

		$em->persist($customer);
		$em->persist($order);
		$em->flush();

		return $this->json([
			'id' => $order->getId(),
			'orderId' => $order->getOrderId(),
			'redirectUrl' => $this->generateUrl('checkout_success', ['orderId' => $order->getOrderId()]),
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



