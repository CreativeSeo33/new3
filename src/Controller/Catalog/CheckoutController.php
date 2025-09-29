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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
use App\Entity\ProductOptionValueAssignment;
use Psr\Log\LoggerInterface;
use App\Service\OrderMailer;
use App\Service\InventoryService;
use App\Exception\InsufficientStockException;

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
		DeliveryContext $deliveryContext,
		OrderMailer $orderMailer,
		LoggerInterface $logger,
		InventoryService $inventory
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
                $sanitize,
                $inventory,
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

                if ($method === 'pvz') {
                    if (!empty($ctx['pickupPointId'])) {
                        $pvzCode = (string)$ctx['pickupPointId'];
                        $point = $em->getRepository(PvzPoints::class)->findOneBy(['code' => $pvzCode]);
                        if ($point && strcasecmp((string)$point->getCity(), (string)$cityName) === 0) {
                            $addr = (string)($point->getAddress() ?? '');
                            $addr = substr(trim($addr), 0, 255);
                            $orderDelivery->setPvz($addr !== '' ? $addr : $pvzCode);
                            $orderDelivery->setPvzCode($pvzCode);
                        } else {
                            // Некорректный ПВЗ/город — расчет менеджером
                            $orderDelivery->setIsCustomCalculate(true);
                        }
                    } else {
                        // ПВЗ не выбран — расчет менеджером
                        $orderDelivery->setIsCustomCalculate(true);
                    }
                }

                if ($method === 'courier') {
                    if (!empty($ctx['address'])) {
                        $orderDelivery->setAddress(substr(trim((string)$ctx['address']), 0, 255));
                    } else {
                        // Адрес не указан — расчет менеджером
                        $orderDelivery->setIsCustomCalculate(true);
                    }
                }

				// Упрощение: больше не привязываем к FIAS, сохраняем только строковое название

                $deliveryProvider = $this->deliveryProviderRegistry->get($method);
                if ($deliveryProvider && !$orderDelivery->getIsCustomCalculate()) {
                    $deliveryProvider->validate($orderDelivery);
                }

                $order->setDelivery($orderDelivery);
				$em->persist($orderDelivery);

                foreach ($cart->getItems() as $it) {
					$op = new OrderProducts();
					$op->setProductId($it->getProduct()->getId());
					$op->setProductName($it->getProductName());
					// Сохраняем в заказ фактическую цену единицы с учетом опций
					$finalUnitPrice = $it->getEffectiveUnitPrice();
					$op->setPrice($finalUnitPrice);
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
						// Определяем sortOrder опции и значения для корректного отображения
						$optionSortOrder = null;
						$valueSortOrder = null;
						if (isset($opt['assignmentId'])) {
							$assignment = $em->getRepository(ProductOptionValueAssignment::class)->find((int)$opt['assignmentId']);
							if ($assignment) {
								$optionSortOrder = $assignment->getOption()?->getSortOrder();
								$valueSortOrder = $assignment->getValue()?->getSortOrder();
							}
						}
						$opo->setValue([
							'optionCode' => $opt['optionCode'] ?? null,
							'valueCode' => $opt['valueCode'] ?? null,
							'valueName' => $opt['valueName'] ?? null,
							'sku' => $opt['sku'] ?? null,
							'optionSortOrder' => $optionSortOrder,
							'valueSortOrder' => $valueSortOrder,
						]);
						$opo->setPrice(isset($opt['price']) ? (int)$opt['price'] : null);
						$em->persist($opo);
					}
				}

				$em->persist($customer);
				$em->persist($order);

				// Закрываем корзину: помечаем истекшей
				$cart->setExpiresAt(new \DateTimeImmutable('-1 second'));

				// Списание остатков: повторная проверка и вычитание в рамках текущей транзакции
				foreach ($cart->getItems() as $it) {
					$product = $it->getProduct();
					$qty = $it->getQty();
					// Извлекаем assignmentIds из связи CartItem.optionAssignments; fallback — snapshot/selectedOptionsData
					$optionAssignmentIds = [];
					$optionAssignments = $it->getOptionAssignments();
					if (!$optionAssignments->isEmpty()) {
						foreach ($optionAssignments as $oa) {
							$optionAssignmentIds[] = (int)$oa->getId();
						}
					} else {
						$opts = $it->getOptionsSnapshot() ?? $it->getSelectedOptionsData() ?? [];
						foreach ($opts as $optRow) {
							$id = $optRow['assignment_id'] ?? ($optRow['assignmentId'] ?? null);
							if (is_numeric($id)) {
								$optionAssignmentIds[] = (int)$id;
							}
						}
					}
					$optionAssignmentIds = array_values(array_unique($optionAssignmentIds));

					// Повторная проверка доступности на момент фиксации
					$inventory->assertAvailable($product, $qty, $optionAssignmentIds);

					if (empty($optionAssignmentIds)) {
						// SIMPLE: блокируем и вычитаем количество на товаре
						$lockedProduct = $em->getRepository(\App\Entity\Product::class)
							->createQueryBuilder('p')
							->where('p.id = :id')
							->setParameter('id', $product->getId())
							->getQuery()
							->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
							->getSingleResult();
						$curr = (int)($lockedProduct->getQuantity() ?? 0);
						if ($curr < $qty) {
							throw new InsufficientStockException(
								"Not enough stock for product '" . ($product->getName() ?? 'product') . "'. Available: {$curr}, requested: {$qty}",
								$curr
							);
						}
						$lockedProduct->setQuantity($curr - $qty);
					} else {
						// VARIABLE: блокируем и вычитаем по каждому assignmentId
						/** @var array<int, int> $deductMap */
						$deductMap = [];
						foreach ($optionAssignmentIds as $aid) {
							$deductMap[$aid] = ($deductMap[$aid] ?? 0) + $qty;
						}
						$changedAssignmentIds = [];
						foreach ($deductMap as $aid => $decQty) {
							$assignment = $em->getRepository(ProductOptionValueAssignment::class)
								->createQueryBuilder('a')
								->where('a.id = :id')
								->setParameter('id', $aid)
								->getQuery()
								->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
								->getOneOrNullResult();
							if ($assignment && $assignment->getProduct()->getId() === $product->getId()) {
								$curr = (int)($assignment->getQuantity() ?? 0);
								if ($curr < $decQty) {
									$optionName = $assignment->getOption()?->getName() ?? 'option';
									$valueName = $assignment->getValue()?->getValue() ?? '';
									throw new InsufficientStockException(
										"Not enough stock for option '{$optionName}: {$valueName}' in product '" . ($product->getName() ?? 'product') . "'. Available: {$curr}, requested: {$decQty}",
										$curr
									);
								}
								$assignment->setQuantity($curr - $decQty);
								$changedAssignmentIds[] = (int)$assignment->getId();
							}
						}
						// Инвалидация кеша инвентаря по изменённым вариантам
						if (!empty($changedAssignmentIds)) {
							$inventory->invalidateCache($changedAssignmentIds);
						}
					}
				}

				// Фиксируем изменения
				$em->flush();

				$createdOrder = $order;
			});
		} catch (InvalidDeliveryDataException $e) {
			return $this->json(['error' => $e->getMessage()], 400);
		} catch (InsufficientStockException $e) {
			return $this->json([
				'error' => 'insufficient_stock',
				'message' => $e->getMessage(),
				'availableQuantity' => $e->getAvailableQuantity(),
			], 409);
		}

		// Очистка checkout-контекста после успешной транзакции
		$checkout->clear();

		// Отправляем письмо-подтверждение покупки синхронно (после фиксации транзакции)
		if ($createdOrder && $createdOrder->getCustomer()?->getEmail()) {
			try {
				$orderMailer->sendConfirmation($createdOrder);
			} catch (\Throwable $e) {
				$logger->error('Order confirmation email failed', [
					'orderId' => $createdOrder->getOrderId(),
					'error' => $e->getMessage(),
				]);
			}
		}

		// Сохраняем orderId в сессию для одноразового показа деталей заказа
        if ($createdOrder) {
            $request->getSession()?->set('order.success.id', $createdOrder->getOrderId());
        }

		return $this->json([
			'id' => $createdOrder?->getId(),
			'orderId' => $createdOrder?->getOrderId(),
			'redirectUrl' => $createdOrder ? $this->generateUrl('checkout_success') : null,
		]);
	}

    #[Route('/checkout/success', name: 'checkout_success', methods: ['GET'])]
    public function success(SessionInterface $session, OrderRepository $orders, EntityManagerInterface $em): Response
    {
        // Читаем и удаляем одноразовый ключ orderId из сессии
        $orderId = $session->get('order.success.id');
        if ($orderId !== null) {
            $session->remove('order.success.id');
        }

        $order = null;
        $productMap = [];

        // Загружаем данные заказа только если есть orderId из flash
        if ($orderId) {
            $order = $orders->findOneBy(['orderId' => $orderId]);
            
            if ($order) {
                // Готовим карту продуктов для изображений (id => Product)
                $productIds = [];
                foreach ($order->getProducts() as $op) {
                    if ($op->getProductId()) {
                        $productIds[] = (int)$op->getProductId();
                    }
                }
                $productIds = array_values(array_unique($productIds));
                
                if (count($productIds) > 0) {
                    $qb = $em->createQueryBuilder()
                        ->select('p', 'img')
                        ->from(\App\Entity\Product::class, 'p')
                        ->leftJoin('p.image', 'img')
                        ->where('p.id IN (:ids)')
                        ->setParameter('ids', $productIds)
                        ->addOrderBy('img.sortOrder', 'ASC');
                    /** @var array<int, \App\Entity\Product> $products */
                    $products = $qb->getQuery()->getResult();
                    foreach ($products as $p) {
                        $productMap[$p->getId()] = $p;
                    }
                }
            }
        }

        return $this->render('catalog/checkout/success.html.twig', [
            'orderId' => $orderId,
            'order' => $order,
            'productMap' => $productMap,
        ]);
    }
}



