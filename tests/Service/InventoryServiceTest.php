<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductOptionValueAssignment;
use App\Entity\Option;
use App\Entity\OptionValue;
use App\Repository\ProductOptionValueAssignmentRepository;
use App\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class InventoryServiceTest extends TestCase
{
    private InventoryService $inventoryService;
    private EntityManagerInterface|MockObject $em;
    private CacheInterface|MockObject $cache;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->inventoryService = new InventoryService(
            $this->em,
            $this->cache,
            $this->logger
        );
    }

    public function testAssertAvailableForBasicProductSuccess(): void
    {
        $product = $this->createProduct(true, 10);

        // Настраиваем кэш для возврата пустого массива (без опций)
        $this->cache->expects($this->never())->method('get');

        $this->inventoryService->assertAvailable($product, 5);

        // Нет исключений - тест пройден
    }

    public function testAssertAvailableForBasicProductInsufficientStock(): void
    {
        $product = $this->createProduct(true, 5);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Not enough stock for product \'Test Product\'. Available: 5, requested: 10');

        $this->inventoryService->assertAvailable($product, 10);
    }

    public function testAssertAvailableForInactiveProduct(): void
    {
        $product = $this->createProduct(false, 10);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product is inactive');

        $this->inventoryService->assertAvailable($product, 5);
    }

    public function testAssertAvailableForInvalidQuantity(): void
    {
        $product = $this->createProduct(true, 10);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Quantity must be >= 1');

        $this->inventoryService->assertAvailable($product, 0);
    }

    public function testAssertOptionsAvailableSuccess(): void
    {
        $product = $this->createProduct(true, 10);
        $assignments = [
            $this->createAssignment($product, 5),
            $this->createAssignment($product, 8)
        ];

        $this->setupCacheForAssignments([1, 2], $assignments);
        $this->setupRepositoryMock($assignments);

        $this->inventoryService->assertOptionsAvailable($product, [1, 2], 3);

        // Нет исключений - тест пройден
    }

    public function testAssertOptionsAvailableInsufficientStock(): void
    {
        $product = $this->createProduct(true, 10);
        $assignments = [
            $this->createAssignment($product, 2), // лимитирующая опция
            $this->createAssignment($product, 10)
        ];

        $this->setupCacheForAssignments([1, 2], $assignments);
        $this->setupRepositoryMock($assignments);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Not enough stock for option \'Color: Red\' in product \'Test Product\'. Available: 2, requested: 5');

        $this->inventoryService->assertOptionsAvailable($product, [1, 2], 5);
    }

    public function testAssertOptionsAvailableMissingAssignment(): void
    {
        $product = $this->createProduct(true, 10);

        // Настраиваем кэш для возврата пустого массива (не найдены назначения)
        $cacheCallback = function ($key, $callback) {
            $item = $this->createMock(ItemInterface::class);
            $item->expects($this->once())->method('expiresAfter');
            $callback($item);
            return [];
        };

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback($cacheCallback);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Option assignments not found: 1');

        $this->inventoryService->assertOptionsAvailable($product, [1], 1);
    }

    public function testAssertOptionsAvailableWrongProduct(): void
    {
        $product1 = $this->createProduct(true, 10);
        // Устанавливаем ID через reflection
        $reflection1 = new \ReflectionClass($product1);
        $idProperty1 = $reflection1->getProperty('id');
        $idProperty1->setAccessible(true);
        $idProperty1->setValue($product1, 1);

        $product2 = $this->createProduct(true, 10);
        // Устанавливаем ID через reflection
        $reflection2 = new \ReflectionClass($product2);
        $idProperty2 = $reflection2->getProperty('id');
        $idProperty2->setAccessible(true);
        $idProperty2->setValue($product2, 2);

        $assignment = $this->createAssignment($product2, 5);

        $this->setupCacheForAssignments([1], [$assignment]);
        $this->setupRepositoryMock([$assignment]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Option assignment does not belong to this product');

        $this->inventoryService->assertOptionsAvailable($product1, [1], 1);
    }

    public function testValidateVariantSkuSuccess(): void
    {
        $product = $this->createProduct(true, 10);
        $assignments = [
            $this->createAssignment($product, 5, 'SKU001'),
            $this->createAssignment($product, 8, 'SKU002')
        ];

        $this->setupCacheForAssignments([1, 2], $assignments);
        $this->setupRepositoryMock($assignments);

        $this->inventoryService->validateVariantSku($product, [1, 2]);

        // Нет исключений - тест пройден
    }

    public function testValidateVariantSkuMissingSku(): void
    {
        $product = $this->createProduct(true, 10);
        $assignments = [
            $this->createAssignment($product, 5, ''), // пустой SKU
            $this->createAssignment($product, 8, 'SKU002')
        ];

        $this->setupCacheForAssignments([1, 2], $assignments);
        $this->setupRepositoryMock($assignments);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('SKU is missing for option \'Color: Red\'');

        $this->inventoryService->validateVariantSku($product, [1, 2]);
    }

    public function testValidateVariantSkuDuplicateSku(): void
    {
        $product = $this->createProduct(true, 10);
        $assignments = [
            $this->createAssignment($product, 5, 'SKU001'),
            $this->createAssignment($product, 8, 'SKU001') // дубликат SKU
        ];

        $this->setupCacheForAssignments([1, 2], $assignments);
        $this->setupRepositoryMock($assignments);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Duplicate SKUs in option combination');

        $this->inventoryService->validateVariantSku($product, [1, 2]);
    }

    public function testGetVariantStockWithOptions(): void
    {
        $product = $this->createProduct(true, 10);
        $assignments = [
            $this->createAssignment($product, 3), // лимитирующая опция
            $this->createAssignment($product, 8)
        ];

        $this->setupCacheForAssignments([1, 2], $assignments);
        $this->setupRepositoryMock($assignments);

        $stock = $this->inventoryService->getVariantStock($product, [1, 2]);

        $this->assertEquals(3, $stock);
    }

    public function testGetVariantStockWithoutOptions(): void
    {
        $product = $this->createProduct(true, 10);

        $stock = $this->inventoryService->getVariantStock($product, []);

        $this->assertEquals(10, $stock);
    }

    private function createProduct(bool $status, ?int $quantity): Product
    {
        $product = new Product();
        // Устанавливаем свойства через reflection, так как setId() не существует
        $reflection = new \ReflectionClass($product);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($product, 1);

        $product->setName('Test Product');
        $product->setStatus($status);
        $product->setQuantity($quantity);
        return $product;
    }

    private function createAssignment(Product $product, int $quantity, string $sku = 'SKU001'): ProductOptionValueAssignment
    {
        $option = new Option();
        // Устанавливаем ID через reflection
        $optionReflection = new \ReflectionClass($option);
        $optionIdProperty = $optionReflection->getProperty('id');
        $optionIdProperty->setAccessible(true);
        $optionIdProperty->setValue($option, 1);
        $option->setName('Color');
        $option->setCode('color');

        $value = new OptionValue();
        // Устанавливаем ID через reflection
        $valueReflection = new \ReflectionClass($value);
        $valueIdProperty = $valueReflection->getProperty('id');
        $valueIdProperty->setAccessible(true);
        $valueIdProperty->setValue($value, 1);
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
        $cacheKey = 'inventory_assignments_' . md5(implode(',', $assignmentIds));

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

    private function setupRepositoryMock(array $assignments): void
    {
        $repo = $this->createMock(ProductOptionValueAssignmentRepository::class);

        // Создаем карту ID -> assignment
        $assignmentMap = [];
        foreach ($assignments as $assignment) {
            $assignmentMap[$assignment->getId()] = $assignment;
        }

        $repo->expects($this->any())
            ->method('find')
            ->willReturnCallback(function ($id) use ($assignmentMap) {
                return $assignmentMap[$id] ?? null;
            });

        $this->em->expects($this->any())
            ->method('getRepository')
            ->with(ProductOptionValueAssignment::class)
            ->willReturn($repo);
    }
}
