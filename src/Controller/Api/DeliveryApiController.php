<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User as AppUser;
use App\Service\{CartManager, DeliveryContext, ShippingCalculator, CartCalculator};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/delivery')]
final class DeliveryApiController extends AbstractController
{
    public function __construct(
        private DeliveryContext $ctx,
        private CartManager $carts,
        private ShippingCalculator $shipping,
        private CartCalculator $calculator,
        private EntityManagerInterface $em,
    ) {}

	#[Route('/context', name: 'api_delivery_context', methods: ['GET'])]
	public function context(): JsonResponse
	{
		return $this->json($this->ctx->get());
	}

	#[Route('/select-city', name: 'api_delivery_select_city', methods: ['POST'])]
	public function selectCity(Request $r): JsonResponse
	{
		$d = json_decode($r->getContent() ?: '[]', true) ?? [];
		$name = trim((string)($d['cityName'] ?? ''));
		if ($name === '') return $this->json(['error' => 'cityName required'], 422);

		$this->ctx->setCity($name, isset($d['cityId']) ? (int)$d['cityId'] : null);

		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;
		$cart = $this->carts->getOrCreateCurrent($userId);
        $this->ctx->syncToCart($cart);
        $cart->setShippingCost(0);
        $cart->setShippingMethod(null);
        $this->calculator->recalculate($cart);
        $this->em->flush();
		return $this->json(['ok' => true]);
	}

	#[Route('/select-method', name: 'api_delivery_select_method', methods: ['POST'])]
	public function selectMethod(Request $r): JsonResponse
	{
		$d = json_decode($r->getContent() ?: '[]', true) ?? [];
		$code = (string)($d['methodCode'] ?? '');
		if ($code === '') return $this->json(['error' => 'methodCode required'], 422);

		$extra = array_intersect_key($d, array_flip(['pickupPointId','address','zip']));
		$this->ctx->setMethod($code, $extra);
		// Дублируем в legacy delivery.type
		$session = $r->getSession();
		if ($session !== null) {
			$legacy = $session->get('delivery', []);
			if (!is_array($legacy)) { $legacy = []; }
			$legacy['type'] = $code;
			$legacy['methodCode'] = $code;
			$session->set('delivery', $legacy);
		}

		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;
		$cart = $this->carts->getOrCreateCurrent($userId);
		$this->ctx->syncToCart($cart);
        $cost = $this->shipping->quote($cart);
        $cart->setShippingCost($cost);
        $this->calculator->recalculate($cart);
        $this->em->flush();

		return $this->json([
			'shippingCost' => $cart->getShippingCost(),
			'total' => $cart->getTotal(),
		]);
	}

	#[Route('/select-pvz', name: 'api_delivery_select_pvz', methods: ['POST'])]
	public function selectPvz(Request $r): JsonResponse
	{
		$d = json_decode($r->getContent() ?: '[]', true) ?? [];
		$pvzCode = (string)($d['pvzCode'] ?? '');
		$address = isset($d['address']) ? trim((string)$d['address']) : '';
		if ($pvzCode === '') return $this->json(['error' => 'pvzCode required'], 422);

		// Убедимся, что метод pvz выбран, и сохраним доп.данные в новую структуру
		$this->ctx->setMethod('pvz', [
			'pickupPointId' => $pvzCode,
			'address' => $address,
		]);

		// Дублируем в legacy delivery.*
		$session = $r->getSession();
		if ($session !== null) {
			$legacy = $session->get('delivery', []);
			if (!is_array($legacy)) { $legacy = []; }
			$legacy['type'] = 'pvz';
			$legacy['pvzCode'] = $pvzCode;
			if ($address !== '') {
				$legacy['address'] = $address;
			}
			$session->set('delivery', $legacy);
		}

		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;
		$cart = $this->carts->getOrCreateCurrent($userId);
		$this->ctx->syncToCart($cart);
		$cost = $this->shipping->quote($cart);
		$cart->setShippingCost($cost);
		$this->calculator->recalculate($cart);
		$this->em->flush();

		return $this->json([
			'shippingCost' => $cart->getShippingCost(),
			'total' => $cart->getTotal(),
		]);
	}
}


