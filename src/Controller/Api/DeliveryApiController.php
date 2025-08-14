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

	#[Route('/context', methods: ['GET'])]
	public function context(): JsonResponse
	{
		return $this->json($this->ctx->get());
	}

	#[Route('/select-city', methods: ['POST'])]
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

	#[Route('/select-method', methods: ['POST'])]
	public function selectMethod(Request $r): JsonResponse
	{
		$d = json_decode($r->getContent() ?: '[]', true) ?? [];
		$code = (string)($d['methodCode'] ?? '');
		if ($code === '') return $this->json(['error' => 'methodCode required'], 422);

		$extra = array_intersect_key($d, array_flip(['pickupPointId','address','zip']));
		$this->ctx->setMethod($code, $extra);

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


