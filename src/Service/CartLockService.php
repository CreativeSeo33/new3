<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use Symfony\Component\Lock\LockFactory;

final class CartLockService
{
	public function __construct(private LockFactory $lockFactory) {}

	public function withCartLock(Cart $cart, callable $callback): void
	{
		$lock = $this->lockFactory->createLock('cart:'.$cart->getId(), 10.0);
		$lock->acquire(true);
		try {
			$callback();
		} finally {
			$lock->release();
		}
	}
}


