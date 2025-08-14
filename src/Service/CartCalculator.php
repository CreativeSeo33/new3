<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;

final class CartCalculator
{
	public function recalculate(Cart $cart): void
	{
		$subtotal = 0;
		foreach ($cart->getItems() as $item) {
			$row = $item->getUnitPrice() * $item->getQty();
			$item->setRowTotal($row);
			$subtotal += $row;
		}

		$discountTotal = 0;
		$total = max(0, $subtotal - $discountTotal);

		$cart->setSubtotal($subtotal);
		$cart->setDiscountTotal($discountTotal);
		$cart->setTotal($total);
	}
}


