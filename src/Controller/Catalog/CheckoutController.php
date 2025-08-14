<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Entity\User as AppUser;
use App\Entity\Order;
use App\Entity\OrderCustomer;
use App\Entity\OrderProducts;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\CartManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CheckoutController extends AbstractController
{
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

		if ($name === '' || $phone === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
			return $this->json(['error' => 'Проверьте корректность данных'], 400);
		}

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



