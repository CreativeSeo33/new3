<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;

final class InventoryService
{
	public function assertAvailable(Product $product, int $qty): void
	{
		if (($product->getStatus() ?? false) !== true) {
			throw new \DomainException('Product is inactive');
		}
		if ($qty < 1) {
			throw new \DomainException('Quantity must be >= 1');
		}
		$stock = $product->getQuantity() ?? 0;
		if ($stock < $qty) {
			throw new \DomainException('Not enough stock');
		}
	}
}


