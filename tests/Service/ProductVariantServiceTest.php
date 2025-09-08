<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductOptionValueAssignment;
use App\Entity\Option;
use App\Entity\OptionValue;
use App\Repository\ProductOptionValueAssignmentRepository;
use App\Service\ProductVariantService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ProductVariantServiceTest extends TestCase
{
    private ProductVariantService $variantService;
    private EntityManagerInterface|MockObject $em;
    private CacheInterface|MockObject $cache;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->variantService = new ProductVariantService(
            $this->em,
            $this->cache,
            $this->logger
        );
    }

    public function testCalculateVariantStockWithOptions(): void
    {
        $product = $this->createProduct();
        $assignments = [
            $this->createAssignment($product, 5),
            $this->createAssignment($product, 3), // лимитирующая
            $this->createAssignment($product, 10)
        ];

        $this->setupCacheForAssignments([1, 2, 3], $assignments);

        $stock = $this->variantService->calculateVariantStock($product, [1, 2, 3]);

        $this->assertEquals(3, $stock);
    }

    public function testCalculateVariantStockWithoutOptions(): void
    {
        $product = $this->createProduct();
        $product->setQuantity(15);

        $stock = $this->variantService->calculateVariantStock($product, []);

        $this->assertEquals(15, $stock);
    }

    public function testValidateVariantCombinationSuccess(): void
    {
        $product = $this->createProduct();
        $assignments = [
            $this->createAssignment($product, 5, 'SKU001'),
            $this->createAssignment($product, 3, 'SKU002')
        ];

        $this->setupCacheForAssignments([1, 2], $assignments);

        $result = $this->variantService->validateVariantCombination($product, [1, 2]);

        $this->assertTrue($result);
    }

    public function testValidateVariantCombinationWithDuplicateOptions(): void
    {
        $product = $this->createProduct();

        // Создаем назначения с одинаковыми опциями
        $option = new Option();
        // Устанавливаем ID через reflection
        $optionReflection = new \ReflectionClass($option);
        $optionIdProperty = $optionReflection->getProperty('id');
        $optionIdProperty->setAccessible(true);
        $optionIdProperty->setValue($option, 1);
        $option->setName('Color');

        $assignment1 = new ProductOptionValueAssignment();
        // Устанавливаем ID через reflection
        $assignment1Reflection = new \ReflectionClass($assignment1);
        $assignment1IdProperty = $assignment1Reflection->getProperty('id');
        $assignment1IdProperty->setAccessible(true);
        $assignment1IdProperty->setValue($assignment1, 1);
        $assignment1->setProduct($product);
        $assignment1->setOption($option);
        $assignment1->setQuantity(5);

        $assignment2 = new ProductOptionValueAssignment();
        // Устанавливаем ID через reflection
        $assignment2Reflection = new \ReflectionClass($assignment2);
        $assignment2IdProperty = $assignment2Reflection->getProperty('id');
        $assignment2IdProperty->setAccessible(true);
        $assignment2IdProperty->setValue($assignment2, 2);
        $assignment2->setProduct($product);
        $assignment2->setOption($option); // та же опция
        $assignment2->setQuantity(3);

        $this->setupCacheForAssignments([1, 2], [$assignment1, $assignment2]);

        $result = $this->variantService->validateVariantCombination($product, [1, 2]);

        $this->assertFalse($result);
    }

    public function testValidateVariantCombinationWithMissingSku(): void
    {
        $product = $this->createProduct();
        $assignments = [
            $this->createAssignment($product, 5, ''), // пустой SKU
            $this->createAssignment($product, 3, 'SKU002')
        ];

        $this->setupCacheForAssignments([1, 2], $assignments);

        $result = $this->variantService->validateVariantCombination($product, [1, 2]);

        $this->assertFalse($result);
    }

    public function testGetVariantByOptionsWithOptions(): void
    {
        $product = $this->createProduct();
        $assignments = [
            $this->createAssignment($product, 5, 'SKU001'),
            $this->createAssignment($product, 3, 'SKU002')
        ];

        $this->setupCacheForAssignments([1, 2], $assignments);

        $variant = $this->variantService->getVariantByOptions($product, [1, 2]);

        $this->assertEquals($product, $variant['product']);
        $this->assertEquals(3, $variant['quantity']); // лимитирующая опция
        $this->assertCount(2, $variant['assignments']);
        $this->assertEquals('SKU001-SKU002', $variant['variant_sku']);
    }

    public function testGetVariantByOptionsWithoutOptions(): void
    {
        $product = $this->createProduct();
        $product->setQuantity(10);

        $variant = $this->variantService->getVariantByOptions($product, []);

        $this->assertEquals($product, $variant['product']);
        $this->assertEquals(10, $variant['quantity']);
        $this->assertEmpty($variant['assignments']);
        $this->assertNull($variant['variant_sku']);
    }

    public function testGetVariantBySku(): void
    {
        $product = $this->createProduct();
        $assignment = $this->createAssignment($product, 5, 'TEST-SKU');

        $cacheKey = 'variant_by_sku_' . md5('TEST-SKU');

        $cacheCallback = function ($key, $callback) use ($assignment) {
            $item = $this->createMock(ItemInterface::class);
            $item->expects($this->once())->method('expiresAfter');
            $callback($item);
            return null; // Для простоты возвращаем null
        };

        $this->cache->expects($this->any())
            ->method('get')
            ->with($cacheKey)
            ->willReturnCallback($cacheCallback);

        $variant = $this->variantService->getVariantBySku('TEST-SKU');

        $this->assertNull($variant);
    }

    public function testGenerateVariantSku(): void
    {
        $product = $this->createProduct();
        $assignments = [
            $this->createAssignment($product, 5, 'SKU002'),
            $this->createAssignment($product, 3, 'SKU001')
        ];

        // Тестируем приватный метод через reflection
        $reflection = new \ReflectionClass($this->variantService);
        $method = $reflection->getMethod('generateVariantSku');
        $method->setAccessible(true);

        $sku = $method->invoke($this->variantService, $assignments);

        // SKU должны быть отсортированы
        $this->assertEquals('SKU001-SKU002', $sku);
    }

    private function createProduct(): Product
    {
        $product = new Product();
        // Устанавливаем свойства через reflection, так как setId() не существует
        $reflection = new \ReflectionClass($product);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($product, 1);

        $product->setName('Test Product');
        $product->setCode(new Ulid());
        $product->setQuantity(10);
        return $product;
    }

    private function createAssignment(Product $product, int $quantity, string $sku = 'SKU001'): ProductOptionValueAssignment
    {
        $option = new Option();
        // Устанавливаем ID через reflection
        $optionReflection = new \ReflectionClass($option);
        $optionIdProperty = $optionReflection->getProperty('id');
        $optionIdProperty->setAccessible(true);
        $optionIdProperty->setValue($option, rand(1, 1000));
        $option->setName('Color');
        $option->setCode('color');

        $value = new OptionValue();
        // Устанавливаем ID через reflection
        $valueReflection = new \ReflectionClass($value);
        $valueIdProperty = $valueReflection->getProperty('id');
        $valueIdProperty->setAccessible(true);
        $valueIdProperty->setValue($value, rand(1, 1000));
        $value->setCode('red');
        $value->setValue('Red');

        $assignment = new ProductOptionValueAssignment();
        // Устанавливаем ID через reflection
        $assignmentReflection = new \ReflectionClass($assignment);
        $assignmentIdProperty = $assignmentReflection->getProperty('id');
        $assignmentIdProperty->setAccessible(true);
        $assignmentIdProperty->setValue($assignment, rand(1, 1000));

        $assignment->setProduct($product);
        $assignment->setOption($option);
        $assignment->setValue($value);
        $assignment->setQuantity($quantity);
        $assignment->setSku($sku);

        return $assignment;
    }

    private function setupCacheForAssignments(array $assignmentIds, array $assignments): void
    {
        $cacheKey = 'variant_assignments_' . md5(implode(',', $assignmentIds));

        $cacheCallback = function ($key, $callback) use ($assignments) {
            $item = $this->createMock(ItemInterface::class);
            $item->expects($this->once())->method('expiresAfter');
            $callback($item);
            return $assignments;
        };

        $this->cache->expects($this->any())
            ->method('get')
            ->with($cacheKey)
            ->willReturnCallback($cacheCallback);
    }
}
