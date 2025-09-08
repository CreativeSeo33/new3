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

final class ProductVariantService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {}

    /**
     * Вычисляет доступное количество для комбинации опций товара
     * Возвращает минимальное количество среди всех опций в комбинации
     */
    public function calculateVariantStock(Product $product, array $optionAssignmentIds): int
    {
        if (empty($optionAssignmentIds)) {
            return $product->getQuantity() ?? 0;
        }

        $assignments = $this->getAssignments($optionAssignmentIds);
        $this->validateAssignmentsForProduct($assignments, $product);

        $minStock = PHP_INT_MAX;
        foreach ($assignments as $assignment) {
            $stock = $assignment->getQuantity() ?? 0;
            if ($stock < $minStock) {
                $minStock = $stock;
            }
        }

        return $minStock === PHP_INT_MAX ? 0 : $minStock;
    }

    /**
     * Проверяет валидность комбинации опций
     */
    public function validateVariantCombination(Product $product, array $optionAssignmentIds): bool
    {
        if (empty($optionAssignmentIds)) {
            return true;
        }

        try {
            $assignments = $this->getAssignments($optionAssignmentIds);
            $this->validateAssignmentsForProduct($assignments, $product);
            $this->validateUniqueOptions($assignments);
            $this->validateSkus($assignments);
            return true;
        } catch (\DomainException $e) {
            $this->logger->warning('Invalid variant combination', [
                'product_id' => $product->getId(),
                'option_ids' => $optionAssignmentIds,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Получает вариант товара по комбинации опций
     * Возвращает массив с информацией о варианте
     */
    public function getVariantByOptions(Product $product, array $optionAssignmentIds): array
    {
        if (empty($optionAssignmentIds)) {
            return [
                'product' => $product,
                'sku' => $product->getCode()?->toString(),
                'quantity' => $product->getQuantity() ?? 0,
                'assignments' => [],
                'variant_sku' => null
            ];
        }

        $assignments = $this->getAssignments($optionAssignmentIds);
        $this->validateAssignmentsForProduct($assignments, $product);

        $variantSku = $this->generateVariantSku($assignments);
        $quantity = $this->calculateVariantStock($product, $optionAssignmentIds);

        return [
            'product' => $product,
            'sku' => $product->getCode()?->toString(),
            'quantity' => $quantity,
            'assignments' => $assignments,
            'variant_sku' => $variantSku
        ];
    }

    /**
     * Получает вариант по SKU
     */
    public function getVariantBySku(string $sku): ?array
    {
        $cacheKey = 'variant_by_sku_' . md5($sku);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($sku) {
            $item->expiresAfter(3600); // 1 час кэша

            /** @var ProductOptionValueAssignmentRepository $repo */
            $repo = $this->em->getRepository(ProductOptionValueAssignment::class);
            $assignment = $repo->findOneBy(['sku' => $sku]);

            if (!$assignment) {
                return null;
            }

            $product = $assignment->getProduct();
            return $this->getVariantByOptions($product, [$assignment->getId()]);
        });
    }

    /**
     * Генерирует SKU для комбинации опций
     */
    private function generateVariantSku(array $assignments): string
    {
        $skus = array_map(fn($assignment) => $assignment->getSku(), $assignments);
        sort($skus); // Для консистентности
        return implode('-', array_filter($skus));
    }

    /**
     * Получает назначения опций с кэшированием
     */
    private function getAssignments(array $optionAssignmentIds): array
    {
        $cacheKey = 'variant_assignments_' . md5(implode(',', $optionAssignmentIds));

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

    /**
     * Проверяет, что все назначения принадлежат указанному товару
     */
    private function validateAssignmentsForProduct(array $assignments, Product $product): void
    {
        foreach ($assignments as $assignment) {
            if ($assignment->getProduct()->getId() !== $product->getId()) {
                throw new \DomainException('Option assignment does not belong to this product');
            }
        }
    }

    /**
     * Проверяет, что все опции в комбинации уникальны (нет дубликатов типов опций)
     */
    private function validateUniqueOptions(array $assignments): void
    {
        $optionIds = array_map(fn($assignment) => $assignment->getOption()->getId(), $assignments);
        if (count($optionIds) !== count(array_unique($optionIds))) {
            throw new \DomainException('Duplicate option types in combination');
        }
    }

    /**
     * Проверяет SKU для всех опций в комбинации
     */
    private function validateSkus(array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $sku = $assignment->getSku();
            if (empty($sku)) {
                $optionName = $assignment->getOption()->getName();
                $valueName = $assignment->getValue()->getValue();
                throw new \DomainException("SKU is missing for option '{$optionName}: {$valueName}'");
            }
        }

        // Проверяем уникальность SKU в комбинации
        $skus = array_map(fn($assignment) => $assignment->getSku(), $assignments);
        if (count($skus) !== count(array_unique($skus))) {
            throw new \DomainException('Duplicate SKUs in option combination');
        }
    }

    /**
     * Получает все доступные комбинации опций для товара
     */
    public function getAvailableCombinations(Product $product): array
    {
        $cacheKey = 'product_combinations_' . $product->getId();

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($product) {
            $item->expiresAfter(600); // 10 минут кэша

            $assignments = $product->getOptionAssignments();
            $combinations = [];

            // Группируем по типу опции
            $optionsByType = [];
            foreach ($assignments as $assignment) {
                $optionId = $assignment->getOption()->getId();
                if (!isset($optionsByType[$optionId])) {
                    $optionsByType[$optionId] = [];
                }
                $optionsByType[$optionId][] = $assignment;
            }

            // Генерируем все возможные комбинации (декартово произведение)
            $combinations = $this->generateCombinations($optionsByType);

            // Фильтруем только доступные комбинации
            return array_filter($combinations, function($combination) {
                return $this->calculateCombinationStock($combination) > 0;
            });
        });
    }

    /**
     * Вычисляет доступное количество для комбинации
     */
    private function calculateCombinationStock(array $combination): int
    {
        if (empty($combination)) {
            return 0;
        }

        $minStock = PHP_INT_MAX;
        foreach ($combination as $assignment) {
            $stock = $assignment->getQuantity() ?? 0;
            if ($stock < $minStock) {
                $minStock = $stock;
            }
        }

        return $minStock === PHP_INT_MAX ? 0 : $minStock;
    }

    /**
     * Генерирует все возможные комбинации опций (декартово произведение)
     */
    private function generateCombinations(array $optionsByType): array
    {
        if (empty($optionsByType)) {
            return [];
        }

        $combinations = [[]];

        foreach ($optionsByType as $optionAssignments) {
            $newCombinations = [];
            foreach ($combinations as $combination) {
                foreach ($optionAssignments as $assignment) {
                    $newCombinations[] = array_merge($combination, [$assignment]);
                }
            }
            $combinations = $newCombinations;
        }

        return $combinations;
    }

    /**
     * Инвалидирует кэш для товара
     */
    public function invalidateProductCache(Product $product): void
    {
        $this->cache->delete('product_combinations_' . $product->getId());
    }

    /**
     * Инвалидирует кэш для комбинации опций
     */
    public function invalidateCombinationCache(array $optionAssignmentIds): void
    {
        if (!empty($optionAssignmentIds)) {
            $cacheKey = 'variant_assignments_' . md5(implode(',', $optionAssignmentIds));
            $this->cache->delete($cacheKey);
        }
    }

    /**
     * Инвалидирует кэш для SKU
     */
    public function invalidateSkuCache(string $sku): void
    {
        $cacheKey = 'variant_by_sku_' . md5($sku);
        $this->cache->delete($cacheKey);
    }
}
