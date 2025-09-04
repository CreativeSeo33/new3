<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\Entity\ProductOptionValueAssignment;

final class CartManager
{
    public function __construct(
		private EntityManagerInterface $em,
		private CartRepository $carts,
		private ProductRepository $products,
        private CartCalculator $calculator,
		private InventoryService $inventory,
		private CartLockService $lock,
		private RequestStack $requestStack,
		private EventDispatcherInterface $events,
        private DeliveryContext $deliveryContext,
        private CheckoutContext $checkoutContext,
    ) {}

    public function getOrCreateCurrent(?int $userId): Cart
    {
        // Этот метод сохранен для обратной совместимости
        // Рекомендуется использовать CartContext напрямую

        if ($userId) {
            $cart = $this->carts->findActiveByUserId($userId);
        } else {
            // Для гостей пробуем найти корзину по cookie cart_id
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $cartIdCookie = $request->cookies->get('cart_id');
                if ($cartIdCookie) {
                    try {
                        $cartId = \Symfony\Component\Uid\Ulid::fromString($cartIdCookie);
                        $cart = $this->carts->findActiveById($cartId);
                    } catch (\InvalidArgumentException) {
                        $cart = null;
                    }
                }
            }
        }

        if (!$cart) {
            $cart = Cart::createNew($userId, (new \DateTimeImmutable())->modify('+180 days'));
            $this->em->persist($cart);
        }

        // Синхронизация контекста доставки и пересчет
        $this->deliveryContext->syncToCart($cart);
        $this->calculator->recalculate($cart);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        // Сохраняем ссылку на корзину в сессии checkout.cart
        $this->checkoutContext->setCartRefFromCart($cart);

        return $cart;
    }

	/**
	 * Генерирует уникальный хеш для набора опций
	 */
	private function generateOptionsHash(array $optionAssignmentIds): string
	{
		// Сортируем ID для обеспечения одинакового хеша при одинаковом наборе опций
		sort($optionAssignmentIds);
		return md5(implode(',', $optionAssignmentIds));
	}

	public function addItem(Cart $cart, int $productId, int $qty, array $optionAssignmentIds = []): Cart
	{

		$this->lock->withCartLock($cart, function() use ($cart, $productId, $qty, $optionAssignmentIds) {
			$this->em->wrapInTransaction(function() use ($cart, $productId, $qty, $optionAssignmentIds) {
				$this->em->lock($cart, LockMode::PESSIMISTIC_WRITE);
				$product = $this->products->find($productId);
				if (!$product) { throw new \DomainException('Product not found'); }
				$this->inventory->assertAvailable($product, $qty);

								// Генерируем хеш опций для уникальности
				$optionsHash = empty($optionAssignmentIds) ? null : $this->generateOptionsHash($optionAssignmentIds);

								// Ищем существующий товар с учетом опций
				// Сначала ищем точное совпадение (товар + опции)
				$item = null;

				if ($optionsHash) {
					// Ищем товар с точно такими же опциями
					$item = $this->carts->findItemForUpdateWithOptions($cart, $product, $optionsHash);
				} else {
					// Ищем товар без опций
					$item = $this->carts->findItemForUpdate($cart, $product);
				}
				
				if ($item) {
					$newQty = $item->getQty() + $qty;
					$this->inventory->assertAvailable($product, $newQty);
					$item->setQty($newQty);
				} else {
					$item = new CartItem();
					$item->setCart($cart);
					$item->setProduct($product);
					$item->setProductName($product->getName() ?? '');
					
					// Базовая цена товара
					$basePrice = $product->getEffectivePrice() ?? ($product->getPrice() ?? 0);
					$item->setUnitPrice($basePrice);
					
					// Обрабатываем опции, если они есть
					if (!empty($optionAssignmentIds)) {
						$optionsPriceModifier = 0;
						$selectedOptionsData = [];
						$optionsSnapshot = [];
						
						foreach ($optionAssignmentIds as $assignmentId) {
							$assignment = $this->em->getRepository(ProductOptionValueAssignment::class)->find($assignmentId);
							if (!$assignment || $assignment->getProduct()->getId() !== $productId) {
								throw new \DomainException('Invalid option assignment');
							}
							
							// Добавляем связь с опцией
							$item->addOptionAssignment($assignment);
							
							// Рассчитываем модификатор цены
							$optionPrice = $assignment->getSalePrice() ?? $assignment->getPrice() ?? 0;
							$optionsPriceModifier += $optionPrice;
							
							// Сохраняем данные опций для истории
							$optionData = [
								'assignmentId' => $assignment->getId(),
								'optionCode' => $assignment->getOption()->getCode(),
								'optionName' => $assignment->getOption()->getName(),
								'valueCode' => $assignment->getValue()->getCode(),
								'valueName' => $assignment->getValue()->getValue(),
								'price' => $optionPrice,
								'sku' => $assignment->getSku(),
							];
							
							$selectedOptionsData[] = $optionData;
							
							// Создаем полный снимок опции для истории
							$optionsSnapshot[] = [
								'assignment_id' => $assignment->getId(),
								'option_code' => $assignment->getOption()->getCode(),
								'option_name' => $assignment->getOption()->getName(),
								'value_code' => $assignment->getValue()->getCode(),
								'value_name' => $assignment->getValue()->getValue(),
								'price' => $assignment->getPrice(),
								'sale_price' => $assignment->getSalePrice(),
								'sku' => $assignment->getSku(),
								'original_sku' => $assignment->getOriginalSku(),
								'height' => $assignment->getHeight(),
								'bulbs_count' => $assignment->getBulbsCount(),
								'lighting_area' => $assignment->getLightingArea(),
								'attributes' => $assignment->getAttributes(),
							];
						}
						
						$item->setOptionsPriceModifier($optionsPriceModifier);
						$item->setSelectedOptionsData($selectedOptionsData);
						$item->setOptionsSnapshot($optionsSnapshot);
						$item->setOptionsHash($optionsHash);
						$item->setEffectiveUnitPrice($basePrice + $optionsPriceModifier);
					} else {
						$item->setEffectiveUnitPrice($basePrice);
						$item->setOptionsHash(null); // Явно устанавливаем null для товаров без опций
					}
					
					$item->setQty($qty);
					$cart->addItem($item);
					$this->em->persist($item);
				}

				$this->calculator->recalculate($cart);
				$cart->setUpdatedAt(new \DateTimeImmutable());
				$this->em->flush();
				
			});
		});
		$this->events->dispatch(new \App\Event\CartUpdatedEvent($cart->getIdString()));
		return $cart;
	}


	public function updateQty(Cart $cart, int $itemId, int $qty): ?Cart
	{
		if ($qty <= 0) return $this->removeItem($cart, $itemId);

		try {
			$this->lock->withCartLock($cart, function() use ($cart, $itemId, $qty) {
				$this->em->wrapInTransaction(function() use ($cart, $itemId, $qty) {
					$this->em->lock($cart, LockMode::PESSIMISTIC_WRITE);
					$item = $this->carts->findItemByIdForUpdate($cart, $itemId);
					if (!$item) throw new \DomainException('Item not found');
					$this->inventory->assertAvailable($item->getProduct(), $qty);
					$item->setQty($qty);
					$this->calculator->recalculate($cart);
					$cart->setUpdatedAt(new \DateTimeImmutable());
					$this->em->flush();
				});
			});
			$this->events->dispatch(new \App\Event\CartUpdatedEvent($cart->getIdString()));
			return $cart;
		} catch (\DomainException) {
			return null; // Товар не найден
		}
	}

	public function removeItem(Cart $cart, int $itemId): ?Cart
	{
		$itemRemoved = false;

		$this->lock->withCartLock($cart, function() use ($cart, $itemId, &$itemRemoved) {
			$this->em->wrapInTransaction(function() use ($cart, $itemId, &$itemRemoved) {
				$this->em->lock($cart, LockMode::PESSIMISTIC_WRITE);
				$item = $this->carts->findItemByIdForUpdate($cart, $itemId);
				if ($item) {
					$this->em->remove($item);
					$itemRemoved = true;
					$this->calculator->recalculate($cart);
					$cart->setUpdatedAt(new \DateTimeImmutable());
					$this->em->flush();
				}
			});
		});

		if ($itemRemoved) {
			$this->events->dispatch(new \App\Event\CartUpdatedEvent($cart->getIdString()));
			return $cart;
		}

		return null; // Товар не найден или не был удален
	}

	public function assignToUser(Cart $cart, int $userId): void
	{
		$cart->setUserId($userId);
		$cart->setToken(null); // Убираем token при присвоении пользователю
		$this->em->flush();
	}

	public function clearCart(Cart $cart): Cart
	{
		$this->lock->withCartLock($cart, function() use ($cart) {
			$this->em->wrapInTransaction(function() use ($cart) {
				$this->em->lock($cart, LockMode::PESSIMISTIC_WRITE);

				// Удаляем все товары из корзины
				foreach ($cart->getItems() as $item) {
					$this->em->remove($item);
				}

				$this->calculator->recalculate($cart);
				$cart->setUpdatedAt(new \DateTimeImmutable());
				$this->em->flush();
			});
		});

		$this->events->dispatch(new \App\Event\CartUpdatedEvent($cart->getIdString()));
		return $cart;
	}

	public function merge(Cart $target, Cart $source): Cart
	{
		$this->lock->withCartLock($target, function() use ($target, $source) {
			$this->em->wrapInTransaction(function() use ($target, $source) {
				$this->em->lock($target, LockMode::PESSIMISTIC_WRITE);
				foreach ($source->getItems() as $srcItem) {
					// Ищем существующий товар с учетом опций
					$optionsHash = $srcItem->getOptionsHash();
					$existing = $optionsHash 
						? $this->carts->findItemForUpdateWithOptions($target, $srcItem->getProduct(), $optionsHash)
						: $this->carts->findItemForUpdate($target, $srcItem->getProduct());
					
					$qty = $existing ? $existing->getQty() + $srcItem->getQty() : $srcItem->getQty();
					$this->inventory->assertAvailable($srcItem->getProduct(), $qty);
					
					if ($existing) {
						$existing->setQty($qty);
					} else {
						$clone = new CartItem();
						$clone->setCart($target);
						$clone->setProduct($srcItem->getProduct());
						$clone->setProductName($srcItem->getProductName());
						$clone->setUnitPrice($srcItem->getUnitPrice());
						$clone->setQty($srcItem->getQty());
						
						// Копируем данные опций
						$clone->setOptionsPriceModifier($srcItem->getOptionsPriceModifier());
						$clone->setEffectiveUnitPrice($srcItem->getEffectiveUnitPrice());
						$clone->setOptionsHash($srcItem->getOptionsHash());
						$clone->setSelectedOptionsData($srcItem->getSelectedOptionsData());
						$clone->setOptionsSnapshot($srcItem->getOptionsSnapshot());
						
						// Копируем связи с опциями
						foreach ($srcItem->getOptionAssignments() as $assignment) {
							$clone->addOptionAssignment($assignment);
						}
						
						$this->em->persist($clone);
					}
				}
				$this->calculator->recalculate($target);
				$target->setUpdatedAt(new \DateTimeImmutable());

				// Помечаем исходную корзину как истекшую и очищаем token
				$source->setExpiresAt(new \DateTimeImmutable('-1 day'));
				$source->setToken(null);

				foreach ($source->getItems() as $it) $this->em->remove($it);
				$this->em->flush();
			});
		});

		return $target;
	}
}


