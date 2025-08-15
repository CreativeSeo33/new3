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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        $request = $this->requestStack->getCurrentRequest();
        $token = $request?->cookies->get('cart_token');

        $cart = null;
        if ($userId) {
            $cart = $this->carts->findActiveByUser($userId);
            if (!$cart && $token) {
                $cart = $this->carts->findActiveByToken($token);
            }
            if (!$cart) {
                $cart = Cart::newGuest();
                $cart->setUserId($userId);
                $this->em->persist($cart);
            }
        } else {
            $cart = $token ? ($this->carts->findActiveByToken($token) ?? Cart::newGuest()) : Cart::newGuest();
            if ($cart->getId() === null) {
                $this->em->persist($cart);
            }
        }

        // Синхронизация контекста доставки и пересчет
        $this->deliveryContext->syncToCart($cart);
        $this->calculator->recalculate($cart);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        // Сохраняем ссылку на корзину в сессии checkout.cart
        $this->checkoutContext->setCartRefFromCart($cart);

        if (!$userId && $request) {
            $request->attributes->set('_set_cart_cookie', $cart->getToken());
        }
        return $cart;
    }

	public function addItem(Cart $cart, int $productId, int $qty): Cart
	{
		$this->lock->withCartLock($cart, function() use ($cart, $productId, $qty) {
			$this->em->wrapInTransaction(function() use ($cart, $productId, $qty) {
				$this->em->lock($cart, LockMode::PESSIMISTIC_WRITE);
				$product = $this->products->find($productId);
				if (!$product) { throw new \DomainException('Product not found'); }
				$this->inventory->assertAvailable($product, $qty);

				$item = $this->carts->findItemForUpdate($cart, $product);
				if ($item) {
					$newQty = $item->getQty() + $qty;
					$this->inventory->assertAvailable($product, $newQty);
					$item->setQty($newQty);
				} else {
					$item = new CartItem();
					$item->setCart($cart);
					$item->setProduct($product);
					$item->setProductName($product->getName() ?? '');
					$item->setUnitPrice($product->getEffectivePrice() ?? ($product->getPrice() ?? 0));
					$item->setQty($qty);
					$cart->addItem($item);
					$this->em->persist($item);
				}

				$this->calculator->recalculate($cart);
				$cart->setUpdatedAt(new \DateTimeImmutable());
				$this->em->flush();
			});
		});
		$this->events->dispatch(new \App\Event\CartUpdatedEvent($cart->getId()));
		return $cart;
	}

	public function updateQty(Cart $cart, int $itemId, int $qty): Cart
	{
		if ($qty <= 0) return $this->removeItem($cart, $itemId);
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
		$this->events->dispatch(new \App\Event\CartUpdatedEvent($cart->getId()));
		return $cart;
	}

	public function removeItem(Cart $cart, int $itemId): Cart
	{
		$this->lock->withCartLock($cart, function() use ($cart, $itemId) {
			$this->em->wrapInTransaction(function() use ($cart, $itemId) {
				$this->em->lock($cart, LockMode::PESSIMISTIC_WRITE);
				$item = $this->carts->findItemByIdForUpdate($cart, $itemId);
				if ($item) $this->em->remove($item);
				$this->calculator->recalculate($cart);
				$cart->setUpdatedAt(new \DateTimeImmutable());
				$this->em->flush();
			});
		});
		$this->events->dispatch(new \App\Event\CartUpdatedEvent($cart->getId()));
		return $cart;
	}

	public function assignToUser(Cart $cart, int $userId): void
	{
		$cart->setUserId($userId);
		$cart->setToken(null);
		$this->em->flush();
	}

	public function merge(Cart $target, Cart $source): Cart
	{
		$this->lock->withCartLock($target, function() use ($target, $source) {
			$this->em->wrapInTransaction(function() use ($target, $source) {
				$this->em->lock($target, LockMode::PESSIMISTIC_WRITE);
				foreach ($source->getItems() as $srcItem) {
					$existing = $this->carts->findItemForUpdate($target, $srcItem->getProduct());
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
						$this->em->persist($clone);
					}
				}
				$this->calculator->recalculate($target);
				$target->setUpdatedAt(new \DateTimeImmutable());
				$source->setExpiresAt(new \DateTimeImmutable('-1 day'));
				foreach ($source->getItems() as $it) $this->em->remove($it);
				$this->em->flush();
			});
		});
		return $target;
	}
}


