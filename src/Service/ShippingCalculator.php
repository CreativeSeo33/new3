<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;

final class ShippingCalculator
{
	public function quote(Cart $cart): int
	{
		$method = $cart->getShippingMethod();
		$data = $cart->getShippingData() ?? [];
		$city = $cart->getShipToCity();

		$subtotal = $cart->getSubtotal();
		if (!$method || !$city) return 0;

		$base = match ($method) {
			'pickup' => 0,
			'courier' => 30000,
			default => 40000,
		};

		if ($subtotal >= 300000) $base = 0;
		$data['etaDays'] = $data['etaDays'] ?? 2;
		$cart->setShippingData($data);

		return $base;
	}
}


