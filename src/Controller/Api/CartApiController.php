<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\CartManager;
use App\Service\CartContext;
use App\Service\CartCalculator;
use App\Service\LivePriceCalculator;
use App\Service\CartDeltaBuilder;
use App\Service\Idempotency\IdempotencyService;
use App\Service\Idempotency\IdempotencyRequestHasher;
use App\Service\Delivery\DeliveryService;
use App\Service\Delivery\Dto\DeliveryCalculationResult;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Entity\User as AppUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, JsonResponse, Response};
use Symfony\Component\Routing\Attribute\Route;
use App\Service\CartLockException;
use App\Entity\Option;
use App\Entity\Cart;
use App\Http\CartWriteGuard;
use App\Http\CartResponse;
use App\Http\CartEtags;
use App\Exception\CartItemNotFoundException;
use App\Exception\InsufficientStockException;
use Psr\Log\LoggerInterface;

/**
 * AI-META v1
 * role: REST API корзины: чтение/мутации, ETag и идемпотентность для безопасных повторов
 * module: Cart
 * dependsOn:
 *   - App\Service\CartManager
 *   - App\Service\CartContext
 *   - App\Service\CartCalculator
 *   - App\Service\LivePriceCalculator
 *   - App\Service\Delivery\DeliveryService
 *   - App\Service\Idempotency\IdempotencyService
 *   - Doctrine\ORM\EntityManagerInterface
 *   - App\Http\CartWriteGuard
 *   - App\Http\CartResponse
 *   - App\Http\CartEtags
 * invariants:
 *   - Все write-операции выполняются под блокировкой корзины и ETag-предикатами
 *   - Источник истины по суммам/позициям — серверные Cart/CartItem (без клиентских пересчётов)
 *   - Тяжёлые расчёты (доставка/LIVE) выполняются вне критической секции
 *   - Идемпотентность write-запросов через заголовок Idempotency-Key
 * transaction: em
 * tests:
 *   - tests/Controller/Api/CartApiOptimizationTest.php
 *   - tests/Service/CartManagerIntegrationTest.php
 * routes:
 *   - GET /api/cart api_cart_get
 *   - POST /api/cart/items api_cart_add_item
 *   - PATCH /api/cart/items/{itemId} api_cart_update_qty
 *   - DELETE /api/cart/items/{itemId} api_cart_remove_item
 *   - DELETE /api/cart api_cart_clear
 *   - PATCH /api/cart api_cart_update_pricing_policy
 *   - POST /api/cart/reprice api_cart_reprice
 *   - POST /api/cart/batch api_cart_batch
 * lastUpdated: 2025-09-15
 */
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
		private CartDeltaBuilder $deltaBuilder,
		private IdempotencyService $idem,
		private IdempotencyRequestHasher $hasher,
		private LoggerInterface $idempotencyLogger,
		private DeliveryService $deliveryService
	) {}

	#[Route('', name: 'api_cart_get', methods: ['GET'])]
    public function getCart(Request $request): JsonResponse
	{
		$user = $this->getUser();
		$userId = $user instanceof AppUser ? $user->getId() : null;

		// Создаем response объект для получения cookies от CartContext
		$response = new JsonResponse();
		$cart = $this->cartContext->getOrCreate($userId, $response);

		// Рассчитываем стоимость доставки
		$deliveryResult = $this->deliveryService->calculateForCart($cart);

		$payload = $this->serializeCart($cart, $deliveryResult);
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
			// Валидация ключа
			if (!preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) || strlen($idempotencyKey) > 255) {
				return new JsonResponse(['error' => 'Invalid Idempotency-Key format'], 400);
			}

			$cartId = $cart->getIdString();
			$built = $this->hasher->build($request, []);
			$endpoint = $built['endpoint'];
			$requestHash = $built['requestHash'];

			$nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
			$begin = $this->idem->begin($idempotencyKey, $cartId, $endpoint, $requestHash, $nowUtc);

			if ($begin->type === 'replay') {
				$this->idempotencyLogger->info('Idempotency replay', [
					'key' => $idempotencyKey,
					'endpoint' => $endpoint,
					'status' => $begin->httpStatus,
				]);
				$response->setStatusCode($begin->httpStatus);
				if ($begin->httpStatus !== 204) {
					$response->setData($begin->responseData);
				} else {
					$response->setContent(null); // Для 204 обнуляем тело
				}
				$this->cartResponse->setResponseHeaders($response, $cart); // ETag, версии, totals, cookie
				$response->headers->set('Idempotency-Replay', 'true');
				$response->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$response->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $response;
			}
			if ($begin->type === 'conflict') {
				$this->idempotencyLogger->warning('Idempotency conflict', [
					'key' => $idempotencyKey,
					'stored_hash' => $begin->storedHash,
					'provided_hash' => $begin->providedHash,
				]);
				$conflictResponse = new JsonResponse([
					'error' => 'idempotency_key_conflict',
					'message' => 'Idempotency key used with different request payload',
					'details' => [
						'provided_hash' => $begin->providedHash,
						'stored_hash' => $begin->storedHash,
						'key_reused_at' => $begin->keyReusedAt?->format(\DATE_ATOM),
					]
				], 409);
				$conflictResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$conflictResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $conflictResponse;
			}
			if ($begin->type === 'in_flight') {
				$inFlightResponse = new JsonResponse([
					'error' => 'idempotency_key_in_flight',
					'message' => 'Request with this idempotency key is already being processed',
					'retry_after' => $begin->retryAfter
				], 409, ['Retry-After' => (string)$begin->retryAfter]);
				$inFlightResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$inFlightResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $inFlightResponse;
			}

			// started — выполняем бизнес-логику ниже
		}

		try {
			// Используем новый метод с отслеживанием изменений
			$result = $this->manager->addItemWithChanges($cart, $productId, $qty, $optionAssignmentIds);
			$cart = $result['cart'];
			$changes = $result['changes'];

		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (InsufficientStockException $e) {
			return $this->json([
				'error' => 'insufficient_stock',
				'message' => $e->getMessage(),
				'availableQuantity' => $e->getAvailableQuantity()
			], 409);
		} catch (\DomainException $e) {
			return $this->json(['error' => $e->getMessage()], 422);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		}

		// Определяем режим ответа
		$responseMode = $this->deltaBuilder->determineResponseMode($request);

		$response->setStatusCode(201);
		$jsonResponse = $this->cartResponse->withCart($response, $cart, $request, $responseMode, $changes);

		// Завершение идемпотентности
		if ($idempotencyKey) {
			$finishPayload = $jsonResponse->getStatusCode() === 204 ? null : json_decode($jsonResponse->getContent(), true);
			$this->idem->finish($idempotencyKey, $jsonResponse->getStatusCode(), $finishPayload);
			$jsonResponse->headers->set('Idempotency-Key', $idempotencyKey);
			$jsonResponse->headers->set('Idempotency-Expires', (new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC')))->format(\DATE_ATOM));
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
			// Валидация ключа
			if (!preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) || strlen($idempotencyKey) > 255) {
				return new JsonResponse(['error' => 'Invalid Idempotency-Key format'], 400);
			}

			$cartId = $cart->getIdString();
			$built = $this->hasher->build($request, ['itemId' => $itemId]);
			$endpoint = $built['endpoint'];
			$requestHash = $built['requestHash'];

			$nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
			$begin = $this->idem->begin($idempotencyKey, $cartId, $endpoint, $requestHash, $nowUtc);

			if ($begin->type === 'replay') {
				$this->idempotencyLogger->info('Idempotency replay', [
					'key' => $idempotencyKey,
					'endpoint' => $endpoint,
					'status' => $begin->httpStatus,
				]);
				$response->setStatusCode($begin->httpStatus);
				if ($begin->httpStatus !== 204) {
					$response->setData($begin->responseData);
				} else {
					$response->setContent(null); // Для 204 обнуляем тело
				}
				$this->cartResponse->setResponseHeaders($response, $cart); // ETag, версии, totals, cookie
				$response->headers->set('Idempotency-Replay', 'true');
				$response->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$response->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $response;
			}
			if ($begin->type === 'conflict') {
				$this->idempotencyLogger->warning('Idempotency conflict', [
					'key' => $idempotencyKey,
					'stored_hash' => $begin->storedHash,
					'provided_hash' => $begin->providedHash,
				]);
				$conflictResponse = new JsonResponse([
					'error' => 'idempotency_key_conflict',
					'message' => 'Idempotency key used with different request payload',
					'details' => [
						'provided_hash' => $begin->providedHash,
						'stored_hash' => $begin->storedHash,
						'key_reused_at' => $begin->keyReusedAt?->format(\DATE_ATOM),
					]
				], 409);
				$conflictResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$conflictResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $conflictResponse;
			}
			if ($begin->type === 'in_flight') {
				$inFlightResponse = new JsonResponse([
					'error' => 'idempotency_key_in_flight',
					'message' => 'Request with this idempotency key is already being processed',
					'retry_after' => $begin->retryAfter
				], 409, ['Retry-After' => (string)$begin->retryAfter]);
				$inFlightResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$inFlightResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $inFlightResponse;
			}

			// started — выполняем бизнес-логику ниже
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
		} catch (InsufficientStockException $e) {
			return $this->json([
				'error' => 'insufficient_stock',
				'message' => $e->getMessage(),
				'availableQuantity' => $e->getAvailableQuantity()
			], 409);
		} catch (\DomainException $e) {
			return $this->json(['error' => $e->getMessage()], 422);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		}

		// Определяем режим ответа
		$responseMode = $this->deltaBuilder->determineResponseMode($request);

		$jsonResponse = $this->cartResponse->withCart($response, $cart, $request, $responseMode, $changes);

		// Завершение идемпотентности
		if ($idempotencyKey) {
			$finishPayload = $jsonResponse->getStatusCode() === 204 ? null : json_decode($jsonResponse->getContent(), true);
			$this->idem->finish($idempotencyKey, $jsonResponse->getStatusCode(), $finishPayload);
			$jsonResponse->headers->set('Idempotency-Key', $idempotencyKey);
			$jsonResponse->headers->set('Idempotency-Expires', (new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC')))->format(\DATE_ATOM));
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
			// Валидация ключа
			if (!preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) || strlen($idempotencyKey) > 255) {
				return new JsonResponse(['error' => 'Invalid Idempotency-Key format'], 400);
			}

			$cartId = $cart->getIdString();
			$built = $this->hasher->build($request, ['itemId' => $itemId]);
			$endpoint = $built['endpoint'];
			$requestHash = $built['requestHash'];

			$nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
			$begin = $this->idem->begin($idempotencyKey, $cartId, $endpoint, $requestHash, $nowUtc);

			if ($begin->type === 'replay') {
				$this->idempotencyLogger->info('Idempotency replay', [
					'key' => $idempotencyKey,
					'endpoint' => $endpoint,
					'status' => $begin->httpStatus,
				]);
				$response->setStatusCode($begin->httpStatus);
				if ($begin->httpStatus !== 204) {
					$response->setData($begin->responseData);
				} else {
					$response->setContent(null); // Для 204 обнуляем тело
				}
				$this->cartResponse->setResponseHeaders($response, $cart); // ETag, версии, totals, cookie
				$response->headers->set('Idempotency-Replay', 'true');
				$response->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$response->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $response;
			}
			if ($begin->type === 'conflict') {
				$this->idempotencyLogger->warning('Idempotency conflict', [
					'key' => $idempotencyKey,
					'stored_hash' => $begin->storedHash,
					'provided_hash' => $begin->providedHash,
				]);
				$conflictResponse = new JsonResponse([
					'error' => 'idempotency_key_conflict',
					'message' => 'Idempotency key used with different request payload',
					'details' => [
						'provided_hash' => $begin->providedHash,
						'stored_hash' => $begin->storedHash,
						'key_reused_at' => $begin->keyReusedAt?->format(\DATE_ATOM),
					]
				], 409);
				$conflictResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$conflictResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $conflictResponse;
			}
			if ($begin->type === 'in_flight') {
				$inFlightResponse = new JsonResponse([
					'error' => 'idempotency_key_in_flight',
					'message' => 'Request with this idempotency key is already being processed',
					'retry_after' => $begin->retryAfter
				], 409, ['Retry-After' => (string)$begin->retryAfter]);
				$inFlightResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$inFlightResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $inFlightResponse;
			}

			// started — выполняем бизнес-логику ниже
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

		// Завершение идемпотентности
		if ($idempotencyKey) {
			$finishPayload = $jsonResponse->getStatusCode() === 204 ? null : json_decode($jsonResponse->getContent(), true);
			$this->idem->finish($idempotencyKey, $jsonResponse->getStatusCode(), $finishPayload);
			$jsonResponse->headers->set('Idempotency-Key', $idempotencyKey);
			$jsonResponse->headers->set('Idempotency-Expires', (new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC')))->format(\DATE_ATOM));
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

		// Проверка Idempotency-Key
		$idempotencyKey = $request->headers->get('Idempotency-Key');
		if ($idempotencyKey) {
			// Валидация ключа
			if (!preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) || strlen($idempotencyKey) > 255) {
				return new JsonResponse(['error' => 'Invalid Idempotency-Key format'], 400);
			}

			$cartId = $cart->getIdString();
			$built = $this->hasher->build($request, []);
			$endpoint = $built['endpoint'];
			$requestHash = $built['requestHash'];

			$nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
			$begin = $this->idem->begin($idempotencyKey, $cartId, $endpoint, $requestHash, $nowUtc);

			if ($begin->type === 'replay') {
				$this->idempotencyLogger->info('Idempotency replay', [
					'key' => $idempotencyKey,
					'endpoint' => $endpoint,
					'status' => $begin->httpStatus,
				]);
				$response->setStatusCode($begin->httpStatus);
				if ($begin->httpStatus !== 204) {
					$response->setData($begin->responseData);
				} else {
					$response->setContent(null); // Для 204 обнуляем тело
				}
				$this->cartResponse->setResponseHeaders($response, $cart); // ETag, версии, totals, cookie
				$response->headers->set('Idempotency-Replay', 'true');
				$response->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$response->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $response;
			}
			if ($begin->type === 'conflict') {
				$this->idempotencyLogger->warning('Idempotency conflict', [
					'key' => $idempotencyKey,
					'stored_hash' => $begin->storedHash,
					'provided_hash' => $begin->providedHash,
				]);
				$conflictResponse = new JsonResponse([
					'error' => 'idempotency_key_conflict',
					'message' => 'Idempotency key used with different request payload',
					'details' => [
						'provided_hash' => $begin->providedHash,
						'stored_hash' => $begin->storedHash,
						'key_reused_at' => $begin->keyReusedAt?->format(\DATE_ATOM),
					]
				], 409);
				$conflictResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$conflictResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $conflictResponse;
			}
			if ($begin->type === 'in_flight') {
				$inFlightResponse = new JsonResponse([
					'error' => 'idempotency_key_in_flight',
					'message' => 'Request with this idempotency key is already being processed',
					'retry_after' => $begin->retryAfter
				], 409, ['Retry-After' => (string)$begin->retryAfter]);
				$inFlightResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$inFlightResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $inFlightResponse;
			}

			// started — выполняем бизнес-логику ниже
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
		$jsonResponse = $this->cartResponse->withCart($response, $cart, $request, $responseMode, $changes);

		// Для 204 не устанавливаем тело вообще
		if ($jsonResponse->getStatusCode() === 204) {
			$jsonResponse->setContent(null);
		}

		// Завершение идемпотентности
		if ($idempotencyKey) {
			$finishPayload = $jsonResponse->getStatusCode() === 204 ? null : json_decode($jsonResponse->getContent(), true);
			$this->idem->finish($idempotencyKey, $jsonResponse->getStatusCode(), $finishPayload);
			$jsonResponse->headers->set('Idempotency-Key', $idempotencyKey);
			$jsonResponse->headers->set('Idempotency-Expires', (new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC')))->format(\DATE_ATOM));
		}

		return $jsonResponse;
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

		// Проверка Idempotency-Key
		$idempotencyKey = $request->headers->get('Idempotency-Key');
		if ($idempotencyKey) {
			// Валидация ключа
			if (!preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) || strlen($idempotencyKey) > 255) {
				return new JsonResponse(['error' => 'Invalid Idempotency-Key format'], 400);
			}

			$cartId = $cart->getIdString();
			$built = $this->hasher->build($request, []);
			$endpoint = $built['endpoint'];
			$requestHash = $built['requestHash'];

			$nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
			$begin = $this->idem->begin($idempotencyKey, $cartId, $endpoint, $requestHash, $nowUtc);

			if ($begin->type === 'replay') {
				$this->idempotencyLogger->info('Idempotency replay', [
					'key' => $idempotencyKey,
					'endpoint' => $endpoint,
					'status' => $begin->httpStatus,
				]);
				$response->setStatusCode($begin->httpStatus);
				if ($begin->httpStatus !== 204) {
					$response->setData($begin->responseData);
				} else {
					$response->setContent(null); // Для 204 обнуляем тело
				}
				$this->cartResponse->setResponseHeaders($response, $cart); // ETag, версии, totals, cookie
				$response->headers->set('Idempotency-Replay', 'true');
				$response->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$response->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $response;
			}
			if ($begin->type === 'conflict') {
				$this->idempotencyLogger->warning('Idempotency conflict', [
					'key' => $idempotencyKey,
					'stored_hash' => $begin->storedHash,
					'provided_hash' => $begin->providedHash,
				]);
				$conflictResponse = new JsonResponse([
					'error' => 'idempotency_key_conflict',
					'message' => 'Idempotency key used with different request payload',
					'details' => [
						'provided_hash' => $begin->providedHash,
						'stored_hash' => $begin->storedHash,
						'key_reused_at' => $begin->keyReusedAt?->format(\DATE_ATOM),
					]
				], 409);
				$conflictResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$conflictResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $conflictResponse;
			}
			if ($begin->type === 'in_flight') {
				$inFlightResponse = new JsonResponse([
					'error' => 'idempotency_key_in_flight',
					'message' => 'Request with this idempotency key is already being processed',
					'retry_after' => $begin->retryAfter
				], 409, ['Retry-After' => (string)$begin->retryAfter]);
				$inFlightResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$inFlightResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $inFlightResponse;
			}

			// started — выполняем бизнес-логику ниже
		}

		$policy = $data['pricingPolicy'] ?? null;
		if (!in_array($policy, ['SNAPSHOT', 'LIVE'], true)) {
			return $this->json(['error' => 'Invalid pricing policy. Allowed: SNAPSHOT, LIVE'], 422);
		}

		try {
			$cart->setPricingPolicy($policy);
			$this->em->flush();

			// Пересчитываем доставку после изменения политики ценообразования
			$deliveryResult = $this->deliveryService->calculateForCart($cart);
		} catch (\InvalidArgumentException $e) {
			return $this->json(['error' => $e->getMessage()], 422);
		}

		$payload = $this->serializeCart($cart, $deliveryResult);
		$jsonResponse = $this->cartResponse->withCart($response, $cart, $request, 'full', []);

		// Завершение идемпотентности
		if ($idempotencyKey) {
			$finishPayload = $jsonResponse->getStatusCode() === 204 ? null : json_decode($jsonResponse->getContent(), true);
			$this->idem->finish($idempotencyKey, $jsonResponse->getStatusCode(), $finishPayload);
			$jsonResponse->headers->set('Idempotency-Key', $idempotencyKey);
			$jsonResponse->headers->set('Idempotency-Expires', (new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC')))->format(\DATE_ATOM));
		}

		return $jsonResponse;
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

		// Проверка Idempotency-Key
		$idempotencyKey = $request->headers->get('Idempotency-Key');
		if ($idempotencyKey) {
			// Валидация ключа
			if (!preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) || strlen($idempotencyKey) > 255) {
				return new JsonResponse(['error' => 'Invalid Idempotency-Key format'], 400);
			}

			$cartId = $cart->getIdString();
			$built = $this->hasher->build($request, []);
			$endpoint = $built['endpoint'];
			$requestHash = $built['requestHash'];

			$nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
			$begin = $this->idem->begin($idempotencyKey, $cartId, $endpoint, $requestHash, $nowUtc);

			if ($begin->type === 'replay') {
				$this->idempotencyLogger->info('Idempotency replay', [
					'key' => $idempotencyKey,
					'endpoint' => $endpoint,
					'status' => $begin->httpStatus,
				]);
				$response->setStatusCode($begin->httpStatus);
				if ($begin->httpStatus !== 204) {
					$response->setData($begin->responseData);
				} else {
					$response->setContent(null); // Для 204 обнуляем тело
				}
				$this->cartResponse->setResponseHeaders($response, $cart); // ETag, версии, totals, cookie
				$response->headers->set('Idempotency-Replay', 'true');
				$response->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$response->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $response;
			}
			if ($begin->type === 'conflict') {
				$this->idempotencyLogger->warning('Idempotency conflict', [
					'key' => $idempotencyKey,
					'stored_hash' => $begin->storedHash,
					'provided_hash' => $begin->providedHash,
				]);
				$conflictResponse = new JsonResponse([
					'error' => 'idempotency_key_conflict',
					'message' => 'Idempotency key used with different request payload',
					'details' => [
						'provided_hash' => $begin->providedHash,
						'stored_hash' => $begin->storedHash,
						'key_reused_at' => $begin->keyReusedAt?->format(\DATE_ATOM),
					]
				], 409);
				$conflictResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$conflictResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $conflictResponse;
			}
			if ($begin->type === 'in_flight') {
				$inFlightResponse = new JsonResponse([
					'error' => 'idempotency_key_in_flight',
					'message' => 'Request with this idempotency key is already being processed',
					'retry_after' => $begin->retryAfter
				], 409, ['Retry-After' => (string)$begin->retryAfter]);
				$inFlightResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$inFlightResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $inFlightResponse;
			}

			// started — выполняем бизнес-логику ниже
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

		// Рассчитываем стоимость доставки
		$deliveryResult = $this->deliveryService->calculateForCart($cart);

		$payload = $this->serializeCart($cart, $deliveryResult);
		$jsonResponse = $this->cartResponse->withCart($response, $cart, $request, 'full', []);

		// Завершение идемпотентности
		if ($idempotencyKey) {
			$finishPayload = $jsonResponse->getStatusCode() === 204 ? null : json_decode($jsonResponse->getContent(), true);
			$this->idem->finish($idempotencyKey, $jsonResponse->getStatusCode(), $finishPayload);
			$jsonResponse->headers->set('Idempotency-Key', $idempotencyKey);
			$jsonResponse->headers->set('Idempotency-Expires', (new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC')))->format(\DATE_ATOM));
		}

		return $jsonResponse;
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

	private function serializeCart($cart, ?DeliveryCalculationResult $deliveryResult = null): array
	{
		$policy = $cart->getPricingPolicy();

		// Построение карты порядка опций: option.code => sortOrder
		$optionCodeToSortOrder = [];
		try {
			$allCodes = [];
			foreach ($cart->getItems() as $it) {
				$opts = $it->getOptionsSnapshot() ?? $it->getSelectedOptionsData() ?? [];
				foreach ($opts as $o) {
					$code = $o['option_code'] ?? ($o['option']['code'] ?? null);
					if (is_string($code) && $code !== '') $allCodes[$code] = true;
				}
			}
			if (!empty($allCodes)) {
				$codes = array_keys($allCodes);
				$qb = $this->em->createQueryBuilder()
					->select('o.code, o.sortOrder')
					->from(Option::class, 'o')
					->where('o.code IN (:codes)')
					->setParameter('codes', $codes);
				$rows = $qb->getQuery()->getArrayResult();
				foreach ($rows as $row) {
					$c = (string)($row['code'] ?? '');
					$s = (int)($row['sortOrder'] ?? 0);
					if ($c !== '') $optionCodeToSortOrder[$c] = $s;
				}
			}
		} catch (\Throwable) {
			$optionCodeToSortOrder = [];
		}

		return [
			'id' => $cart->getIdString(),
			'version' => $cart->getVersion(),
			'currency' => $cart->getCurrency(),
			'pricingPolicy' => $policy,
			'subtotal' => $cart->getSubtotal(),
			'discountTotal' => $cart->getDiscountTotal(),
			'total' => $cart->getTotal(),
			'totalItemQuantity' => $cart->getTotalItemQuantity(),
			'shipping' => [
				'method' => $cart->getShippingMethod(),
				'cost' => $deliveryResult?->cost, // null означает "Расчет менеджером"
				'city' => $cart->getShipToCity(),
				'data' => [
					'term' => $deliveryResult?->term ?? null,
					'message' => $deliveryResult?->message ?? null,
					'isFree' => $deliveryResult?->isFree ?? false,
					'requiresManagerCalculation' => $deliveryResult?->requiresManagerCalculation ?? false,
				] + ($cart->getShippingData() ?? []),
			],
			'items' => array_map(function($i) use ($policy, $optionCodeToSortOrder) {
				$data = [
					'id' => $i->getId(),
					'productId' => $i->getProduct()->getId(),
					'name' => $i->getProductName(),
					'slug' => $i->getProduct()->getSlug(),
					'url' => ($i->getProduct()->getSlug() !== null)
						? $this->generateUrl('catalog_product_show', ['slug' => $i->getProduct()->getSlug()])
						: null,
					'unitPrice' => $i->getUnitPrice(),
					'optionsPriceModifier' => $i->getOptionsPriceModifier(),
					'effectiveUnitPrice' => $i->getEffectiveUnitPrice(),
					'qty' => $i->getQty(),
					'rowTotal' => $i->getRowTotal(),
					'pricedAt' => $i->getPricedAt()->format(DATE_ATOM),
					'selectedOptions' => (function() use ($i, $optionCodeToSortOrder) {
						$opts = $i->getOptionsSnapshot() ?? $i->getSelectedOptionsData() ?? [];
						if (!is_array($opts) || count($opts) < 2) return $opts;
						usort($opts, function($a, $b) use ($optionCodeToSortOrder) {
							$codeA = $a['option_code'] ?? ($a['option']['code'] ?? null);
							$codeB = $b['option_code'] ?? ($b['option']['code'] ?? null);
							$sa = (is_string($codeA) && isset($optionCodeToSortOrder[$codeA])) ? (int)$optionCodeToSortOrder[$codeA] : PHP_INT_MAX;
							$sb = (is_string($codeB) && isset($optionCodeToSortOrder[$codeB])) ? (int)$optionCodeToSortOrder[$codeB] : PHP_INT_MAX;
							return $sa <=> $sb;
						});
						return $opts;
					})(),
					'optionsHash' => $i->getOptionsHash(),
				];

				// Lightweight картинка для мини-корзины
				try {
					$firstImage = $i->getProduct()->getImage()->first();
					$raw = $firstImage ? $firstImage->getImageUrl() : null;
					$data['firstImageUrl'] = $raw;
					if ($raw) {
						if (str_starts_with($raw, '/media/cache/')) {
							$parts = explode('/', ltrim($raw, '/'));
							$data['firstImageSmUrl'] = count($parts) >= 4
								? '/media/cache/resolve/sm/' . implode('/', array_slice($parts, 3))
								: $raw;
						} else {
							$data['firstImageSmUrl'] = '/media/cache/resolve/sm/' . ltrim($raw, '/');
						}
					} else {
						$data['firstImageSmUrl'] = null;
					}
				} catch (\Throwable) {
					$data['firstImageUrl'] = null;
					$data['firstImageSmUrl'] = null;
				}

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
		try {
			$deliveryResult = $this->deliveryService->calculateForCart($cart);
		} catch (\Exception $e) {
			$deliveryResult = null;
		}

		return new JsonResponse([
			'error' => 'precondition_failed',
			'message' => 'Cart version mismatch',
			'currentVersion' => $cart->getVersion(),
			'cart' => $this->serializeCart($cart, $deliveryResult),
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
			// Валидация ключа
			if (!preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) || strlen($idempotencyKey) > 255) {
				return new JsonResponse(['error' => 'Invalid Idempotency-Key format'], 400);
			}

			$cartId = $cart->getIdString();
			$built = $this->hasher->build($request, []);
			$endpoint = $built['endpoint'];
			$requestHash = $built['requestHash'];

			$nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
			$begin = $this->idem->begin($idempotencyKey, $cartId, $endpoint, $requestHash, $nowUtc);

			if ($begin->type === 'replay') {
				$this->idempotencyLogger->info('Idempotency replay', [
					'key' => $idempotencyKey,
					'endpoint' => $endpoint,
					'status' => $begin->httpStatus,
				]);
				$response->setStatusCode($begin->httpStatus);
				if ($begin->httpStatus !== 204) {
					$response->setData($begin->responseData);
				} else {
					$response->setContent(null); // Для 204 обнуляем тело
				}
				$this->cartResponse->setResponseHeaders($response, $cart); // ETag, версии, totals, cookie
				$response->headers->set('Idempotency-Replay', 'true');
				$response->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$response->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $response;
			}
			if ($begin->type === 'conflict') {
				$this->idempotencyLogger->warning('Idempotency conflict', [
					'key' => $idempotencyKey,
					'stored_hash' => $begin->storedHash,
					'provided_hash' => $begin->providedHash,
				]);
				$conflictResponse = new JsonResponse([
					'error' => 'idempotency_key_conflict',
					'message' => 'Idempotency key used with different request payload',
					'details' => [
						'provided_hash' => $begin->providedHash,
						'stored_hash' => $begin->storedHash,
						'key_reused_at' => $begin->keyReusedAt?->format(\DATE_ATOM),
					]
				], 409);
				$conflictResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$conflictResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $conflictResponse;
			}
			if ($begin->type === 'in_flight') {
				$inFlightResponse = new JsonResponse([
					'error' => 'idempotency_key_in_flight',
					'message' => 'Request with this idempotency key is already being processed',
					'retry_after' => $begin->retryAfter
				], 409, ['Retry-After' => (string)$begin->retryAfter]);
				$inFlightResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$exp = $begin->expiresAt ?? new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC'));
				$inFlightResponse->headers->set('Idempotency-Expires', $exp->format(\DATE_ATOM));
				return $inFlightResponse;
			}

			// started — выполняем бизнес-логику ниже
		}

		try {
			$batchResult = $this->manager->executeBatch($cart, $operations, $atomic);

			if (!$batchResult['success']) {
				// Частичный успех или полный провал в атомарном режиме
				$jsonResponse = $this->cartResponse->withBatchError(
					$response,
					$cart,
					$atomic ? 'Batch operation failed' : 'Some operations failed',
					$batchResult['results'],
					$atomic ? 400 : 207 // 207 Multi-Status для частичного успеха
				);

				// Завершение идемпотентности
				if ($idempotencyKey) {
					$finishPayload = $jsonResponse->getStatusCode() === 204 ? null : json_decode($jsonResponse->getContent(), true);
					$this->idem->finish($idempotencyKey, $jsonResponse->getStatusCode(), $finishPayload);
					$jsonResponse->headers->set('Idempotency-Key', $idempotencyKey);
					$jsonResponse->headers->set('Idempotency-Expires', (new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC')))->format(\DATE_ATOM));
				}

				return $jsonResponse;
			}

			// Успешное выполнение
			$cart = $batchResult['cart'];
			$batchPayload = [
				'version' => $cart->getVersion(),
				'results' => $batchResult['results'],
				'changedItems' => array_map(function($change) {
					if ($change['type'] === 'changed') {
						$item = $change['item'];
						return [
							'id' => $item->getId(),
							'qty' => $item->getQty(),
							'rowTotal' => $item->getRowTotal(),
							'effectiveUnitPrice' => $item->getEffectiveUnitPrice(),
						];
					}
					return null;
				}, array_filter($batchResult['changes'], fn($c) => $c['type'] === 'changed')),
				'removedItemIds' => array_map(
					fn($c) => $c['itemId'],
					array_filter($batchResult['changes'], fn($c) => $c['type'] === 'removed')
				),
				'totals' => [
					'itemsCount' => $cart->getItems()->count(),
					'subtotal' => $cart->getSubtotal(),
					'discountTotal' => $cart->getDiscountTotal(),
					'total' => $cart->getTotal(),
				],
			];

			$jsonResponse = $this->cartResponse->withBatchResult(
				$response,
				$cart,
				$batchResult['results'],
				$batchResult['changes']
			);

			// Завершение идемпотентности
			if ($idempotencyKey) {
				$finishPayload = $jsonResponse->getStatusCode() === 204 ? null : json_decode($jsonResponse->getContent(), true);
				$this->idem->finish($idempotencyKey, $jsonResponse->getStatusCode(), $finishPayload);
				$jsonResponse->headers->set('Idempotency-Key', $idempotencyKey);
				$jsonResponse->headers->set('Idempotency-Expires', (new \DateTimeImmutable('+48 hours', new \DateTimeZone('UTC')))->format(\DATE_ATOM));
			}

			return $jsonResponse;

		} catch (CartLockException $e) {
			return $this->createBusyResponse($e);
		} catch (InsufficientStockException $e) {
			return $this->json([
				'error' => 'insufficient_stock',
				'message' => $e->getMessage(),
				'availableQuantity' => $e->getAvailableQuantity()
			], 409);
		} catch (\DomainException $e) {
			return new JsonResponse(['error' => $e->getMessage()], 422);
		} catch (\Doctrine\ORM\OptimisticLockException|\Doctrine\DBAL\Exception\DeadlockException $e) {
			return $this->createConflictResponse();
		} catch (\Exception $e) {
			return new JsonResponse(['error' => 'Batch operation failed: ' . $e->getMessage()], 500);
		}
	}



}


