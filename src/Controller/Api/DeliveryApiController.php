<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User as AppUser;
use App\Service\{CartManager, DeliveryContext, CartCalculator};
use App\Service\Delivery\DeliveryService;
use App\Entity\PvzPoints;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use App\Http\CartWriteGuard;
use App\Http\CartResponse;

/**
 * AI-META v1
 * role: Внутренний API управления контекстом доставки и котировками для корзины
 * module: Delivery
 * dependsOn:
 *   - App\Service\DeliveryContext
 *   - App\Service\CartManager
 *   - App\Service\Delivery\DeliveryService
 *   - App\Service\CartCalculator
 *   - Doctrine\ORM\EntityManagerInterface
 *   - App\Http\CartWriteGuard
 *   - App\Http\CartResponse
 * invariants:
 *   - Изменения контекста доставки синхронизируются в Cart
 *   - Write-операции требуют ETag-предикаты через CartWriteGuard
 * transaction: em
 * routes:
 *   - GET /api/delivery/context api_delivery_context
 *   - POST /api/delivery/select-city api_delivery_select_city
 *   - POST /api/delivery/select-method api_delivery_select_method
 *   - POST /api/delivery/select-pvz api_delivery_select_pvz
 *   - GET /api/delivery/pvz-points api_delivery_pvz_points
 * lastUpdated: 2025-09-15
 */
#[Route('/api/delivery')]
final class DeliveryApiController extends AbstractController
{
    public function __construct(
        private DeliveryContext $ctx,
        private CartManager $carts,
        private DeliveryService $delivery,
        private CartCalculator $calculator,
        private EntityManagerInterface $em,
        private CartWriteGuard $guard,
        private CartResponse $cartResponse,
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

        // Нормализуем KLADR-код (строка до 13 символов)
        $kladr = null;
        if (isset($d['cityKladr'])) {
            $kladr = (string)$d['cityKladr'];
            $kladr = preg_replace('/\D+/', '', $kladr) ?? '';
            $kladr = $kladr !== '' ? substr($kladr, 0, 13) : null;
        }

        // Упрощаем: сохраняем только название (id/kladr игнорируются)
        $this->ctx->setCity($name, null, null);

		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;
		$cart = $this->carts->getOrCreateForWrite($userId);

		// Проверяем предикаты записи
		try {
			$this->guard->assertPrecondition($r, $cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException $e) {
			return new JsonResponse(['error' => 'precondition_failed', 'message' => 'Cart ETag mismatch'], 412);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException $e) {
			return new JsonResponse(['error' => 'precondition_required', 'message' => $e->getMessage()], 428);
		}

        $this->ctx->syncToCart($cart);
        $cart->setShippingCost(0);
        $cart->setShippingMethod(null);
        $this->calculator->recalculate($cart);
        $this->em->flush();

        $payload = ['ok' => true];
        $response = new JsonResponse();
        return $this->cartResponse->withCart($response, $cart, $r, 'full', []);
	}

	#[Route('/select-method', name: 'api_delivery_select_method', methods: ['POST'])]
	public function selectMethod(Request $r): JsonResponse
	{
		$d = json_decode($r->getContent() ?: '[]', true) ?? [];
		$code = (string)($d['methodCode'] ?? '');
		// Ранний trim/лимит для адреса
		if (isset($d['address'])) {
			$addr = trim((string)$d['address']);
			$addr = preg_replace('/[\x00-\x1F\x7F]/u', '', $addr) ?? '';
			$d['address'] = mb_substr($addr, 0, 255);
		}
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
		$cart = $this->carts->getForWriteLight($userId);

		// Проверяем предикаты записи
		try {
			$this->guard->assertPrecondition($r, $cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException $e) {
			return new JsonResponse(['error' => 'precondition_failed', 'message' => 'Cart ETag mismatch'], 412);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException $e) {
			return new JsonResponse(['error' => 'precondition_required', 'message' => $e->getMessage()], 428);
		}

		$this->ctx->syncToCart($cart);
        $cost = $this->delivery->quote($cart);
        $cart->setShippingCost($cost);
        $this->calculator->recalculateShippingAndDiscounts($cart);
        $this->em->flush();

        $response = new JsonResponse();
        return $this->cartResponse->withCart($response, $cart, $r, 'summary', []);
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
		$cart = $this->carts->getOrCreateForWrite($userId);

		// Проверяем предикаты записи
		try {
			$this->guard->assertPrecondition($r, $cart);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException $e) {
			return new JsonResponse(['error' => 'precondition_failed', 'message' => 'Cart ETag mismatch'], 412);
		} catch (\Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException $e) {
			return new JsonResponse(['error' => 'precondition_required', 'message' => $e->getMessage()], 428);
		}

		$this->ctx->syncToCart($cart);
		$cost = $this->delivery->quote($cart);
		$cart->setShippingCost($cost);
		$this->calculator->recalculate($cart);
		$this->em->flush();

		$payload = [
			'shippingCost' => $cart->getShippingCost(),
			'total' => $cart->getTotal(),
		];
		$response = new JsonResponse();
		return $this->cartResponse->withCart($response, $cart, $r, 'full', []);
	}

	/**
	 * GET /api/delivery/pvz-points?city=...&page=1&itemsPerPage=20
	 * Упрощенный список ПВЗ для фронта checkout: возвращает массив точек.
	 */
	#[Route('/pvz-points', name: 'api_delivery_pvz_points', methods: ['GET'])]
	public function pvzPoints(Request $request): JsonResponse
	{
		$city = trim((string)$request->query->get('city', ''));
		$cityId = (int)$request->query->get('cityId', 0);
		if ($cityId <= 0 && $city === '') {
			return $this->json(['error' => 'city or cityId is required'], 422);
		}

		$defaultLimit = (int) $this->getParameter('delivery.points.default_limit');
		$maxLimit = (int) $this->getParameter('delivery.points.max_limit');
		$page = max(1, (int)$request->query->get('page', 1));
		$limitReq = (int)$request->query->get('itemsPerPage', $defaultLimit);
		$limit = $limitReq > 0 ? min($limitReq, $maxLimit) : $defaultLimit;
		$offset = ($page - 1) * $limit;

		$repo = $this->em->getRepository(PvzPoints::class);
		$qb = $repo->createQueryBuilder('p')
			->select('p.code AS code, p.name AS name, p.address AS address, p.city AS city')
			->orderBy('p.name', 'ASC')
			->setFirstResult($offset)
			->setMaxResults($limit);
		if ($cityId > 0) {
			$qb->andWhere('IDENTITY(p.cityFias) = :cityId')->setParameter('cityId', $cityId);
		} else {
			$qb->andWhere('LOWER(TRIM(p.city)) = :city')
			   ->setParameter('city', mb_strtolower(trim($city)));
		}

		$data = $qb->getQuery()->getArrayResult();

		$points = array_map(static function (array $row): array {
			return [
				'code' => $row['code'] ?? null,
				'name' => $row['name'] ?? null,
				'address' => $row['address'] ?? null,
				'city' => $row['city'] ?? null,
			];
		}, $data);

		return $this->json($points);
	}
}


