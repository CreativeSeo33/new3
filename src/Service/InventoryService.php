<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductOptionValueAssignment;
use App\Repository\ProductOptionValueAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class InventoryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {}

	public function assertAvailable(Product $product, int $qty, array $optionAssignmentIds = []): void
	{
		$this->validateBasicRequirements($product, $qty);

		if (empty($optionAssignmentIds)) {
			// Проверяем базовый товар
			$this->assertProductAvailable($product, $qty);
		} else {
			// Проверяем доступность комбинации опций
			$this->assertOptionsAvailable($product, $optionAssignmentIds, $qty);
		}
	}

	private function validateBasicRequirements(Product $product, int $qty): void
	{
		if (($product->getStatus() ?? false) !== true) {
			throw new \DomainException('Product is inactive');
		}
		if ($qty < 1) {
			throw new \DomainException('Quantity must be >= 1');
		}
	}

	private function assertProductAvailable(Product $product, int $qty): void
	{
		$stock = $product->getQuantity() ?? 0;
		if ($stock < $qty) {
			throw new \DomainException("Not enough stock for product '{$product->getName()}'. Available: {$stock}, requested: {$qty}");
		}
	}

	public function assertOptionsAvailable(Product $product, array $optionAssignmentIds, int $qty): void
	{
		$this->logger->debug('Checking options availability', [
			'product_id' => $product->getId(),
			'option_ids' => $optionAssignmentIds,
			'quantity' => $qty
		]);

		$assignments = $this->getOptionAssignments($optionAssignmentIds);
		$this->validateAssignments($assignments, $product, $optionAssignmentIds);

		// Определяем лимитирующую опцию (с наименьшим количеством)
		$limitingAssignment = $this->findLimitingAssignment($assignments);

		if (!$limitingAssignment) {
			throw new \DomainException('No valid option assignments found');
		}

		$availableStock = $limitingAssignment->getQuantity() ?? 0;
		if ($availableStock < $qty) {
			$optionName = $limitingAssignment->getOption()->getName();
			$valueName = $limitingAssignment->getValue()->getValue();
			throw new \DomainException(
				"Not enough stock for option '{$optionName}: {$valueName}' in product '{$product->getName()}'. " .
				"Available: {$availableStock}, requested: {$qty}"
			);
		}
	}

	private function getOptionAssignments(array $optionAssignmentIds): array
	{
		$cacheKey = 'inventory_assignments_' . md5(implode(',', $optionAssignmentIds));

		return $this->cache->get($cacheKey, function (ItemInterface $item) use ($optionAssignmentIds) {
			$item->expiresAfter(300); // 5 минут кэша

			/** @var ProductOptionValueAssignmentRepository $repo */
			$repo = $this->em->getRepository(ProductOptionValueAssignment::class);
			$assignments = [];

			foreach ($optionAssignmentIds as $assignmentId) {
				$assignment = $repo->find((int)$assignmentId);
				if ($assignment) {
					$assignments[] = $assignment;
				}
			}

			return $assignments;
		});
	}

	private function validateAssignments(array $assignments, Product $product, array $optionAssignmentIds): void
	{
		if (count($assignments) !== count($optionAssignmentIds)) {
			$foundIds = array_map(fn($a) => $a->getId(), $assignments);
			$missingIds = array_diff($optionAssignmentIds, $foundIds);
			throw new \DomainException('Option assignments not found: ' . implode(', ', $missingIds));
		}

		foreach ($assignments as $assignment) {
			if ($assignment->getProduct()->getId() !== $product->getId()) {
				throw new \DomainException('Option assignment does not belong to this product');
			}
		}
	}

	private function findLimitingAssignment(array $assignments): ?ProductOptionValueAssignment
	{
		$limitingAssignment = null;
		$minStock = PHP_INT_MAX;

		foreach ($assignments as $assignment) {
			$stock = $assignment->getQuantity() ?? 0;
			if ($stock < $minStock) {
				$minStock = $stock;
				$limitingAssignment = $assignment;
			}
		}

		return $limitingAssignment;
	}

	public function getVariantStock(Product $product, array $optionAssignmentIds): ?int
	{
		if (empty($optionAssignmentIds)) {
			return $product->getQuantity();
		}

		$assignments = $this->getOptionAssignments($optionAssignmentIds);
		$this->validateAssignments($assignments, $product, $optionAssignmentIds);

		$limitingAssignment = $this->findLimitingAssignment($assignments);
		return $limitingAssignment ? $limitingAssignment->getQuantity() : null;
	}

	public function validateVariantSku(Product $product, array $optionAssignmentIds): void
	{
		if (empty($optionAssignmentIds)) {
			return;
		}

		$assignments = $this->getOptionAssignments($optionAssignmentIds);

		foreach ($assignments as $assignment) {
			$sku = $assignment->getSku();
			if (empty($sku)) {
				$optionName = $assignment->getOption()->getName();
				$valueName = $assignment->getValue()->getValue();
				throw new \DomainException("SKU is missing for option '{$optionName}: {$valueName}'");
			}
		}

		// Проверяем уникальность комбинации SKU
		$skus = array_map(fn($a) => $a->getSku(), $assignments);
		if (count($skus) !== count(array_unique($skus))) {
			throw new \DomainException('Duplicate SKUs in option combination');
		}
	}

	public function invalidateCache(array $optionAssignmentIds = []): void
	{
		if (!empty($optionAssignmentIds)) {
			$cacheKey = 'inventory_assignments_' . md5(implode(',', $optionAssignmentIds));
			$this->cache->delete($cacheKey);
		}
	}
}


