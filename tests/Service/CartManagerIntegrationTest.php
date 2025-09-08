<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\ProductOptionValueAssignment;
use App\Entity\Option;
use App\Entity\OptionValue;
use App\Service\CartManager;
use App\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CartManagerIntegrationTest extends KernelTestCase
{
    private CartManager $cartManager;
    private InventoryService $inventoryService;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->cartManager = $container->get(CartManager::class);
        $this->inventoryService = $container->get(InventoryService::class);
        $this->em = $container->get(EntityManagerInterface::class);

        // Очищаем базу данных перед каждым тестом
        $this->clearDatabase();
    }

    public function testAddItemWithOptionsAvailable(): void
    {
        // Создаем продукт с опциями
        $product = $this->createProduct('Test Product', 100);
        $redOption = $this->createOptionAssignment($product, 'Color', 'Red', 10, 'COLOR-RED');
        $sizeMOption = $this->createOptionAssignment($product, 'Size', 'M', 15, 'SIZE-M');

        $this->em->flush();

        // Создаем корзину
        $cart = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $this->em->persist($cart);
        $this->em->flush();

        // Добавляем товар с опциями в корзину
        $updatedCart = $this->cartManager->addItem($cart, $product->getId(), 5, [$redOption->getId(), $sizeMOption->getId()]);

        // Проверяем, что товар добавлен
        $this->assertCount(1, $updatedCart->getItems());
        $item = $updatedCart->getItems()->first();

        $this->assertEquals(5, $item->getQty());
        $this->assertCount(2, $item->getOptionAssignments());
        $this->assertEquals($product->getId(), $item->getProduct()->getId());
    }

    public function testAddItemWithOptionsInsufficientStock(): void
    {
        // Создаем продукт с опциями, где одна опция имеет мало товара
        $product = $this->createProduct('Test Product', 100);
        $redOption = $this->createOptionAssignment($product, 'Color', 'Red', 3, 'COLOR-RED'); // только 3 в наличии

        $this->em->flush();

        // Создаем корзину
        $cart = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $this->em->persist($cart);
        $this->em->flush();

        // Пытаемся добавить 5 товаров (больше, чем доступно)
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Not enough stock for option \'Color: Red\'');

        $this->cartManager->addItem($cart, $product->getId(), 5, [$redOption->getId()]);
    }

    public function testUpdateItemQuantityWithOptions(): void
    {
        // Создаем продукт с опциями
        $product = $this->createProduct('Test Product', 100);
        $redOption = $this->createOptionAssignment($product, 'Color', 'Red', 10, 'COLOR-RED');

        $this->em->flush();

        // Создаем корзину и добавляем товар
        $cart = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $this->em->persist($cart);
        $this->em->flush();

        $updatedCart = $this->cartManager->addItem($cart, $product->getId(), 2, [$redOption->getId()]);
        $item = $updatedCart->getItems()->first();

        // Обновляем количество
        $finalCart = $this->cartManager->updateQty($updatedCart, $item->getId(), 5);

        $updatedItem = $finalCart->getItems()->first();
        $this->assertEquals(5, $updatedItem->getQty());
    }

    public function testMergeCartsWithOptions(): void
    {
        // Создаем продукт с опциями
        $product = $this->createProduct('Test Product', 100);
        $redOption = $this->createOptionAssignment($product, 'Color', 'Red', 20, 'COLOR-RED');

        $this->em->flush();

        // Создаем две корзины
        $cart1 = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $cart2 = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $this->em->persist($cart1);
        $this->em->persist($cart2);
        $this->em->flush();

        // Добавляем товары в обе корзины
        $cart1 = $this->cartManager->addItem($cart1, $product->getId(), 3, [$redOption->getId()]);
        $cart2 = $this->cartManager->addItem($cart2, $product->getId(), 2, [$redOption->getId()]);

        // Сливаем корзины
        $mergedCart = $this->cartManager->merge($cart1, $cart2);

        // Проверяем результат слияния
        $this->assertCount(1, $mergedCart->getItems());
        $item = $mergedCart->getItems()->first();
        $this->assertEquals(5, $item->getQty()); // 3 + 2
        $this->assertCount(1, $item->getOptionAssignments());
    }

    public function testAddItemWithoutOptions(): void
    {
        // Создаем простой продукт без опций
        $product = $this->createProduct('Simple Product', 50);

        $this->em->flush();

        // Создаем корзину
        $cart = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $this->em->persist($cart);
        $this->em->flush();

        // Добавляем товар без опций
        $updatedCart = $this->cartManager->addItem($cart, $product->getId(), 5, []);

        // Проверяем, что товар добавлен
        $this->assertCount(1, $updatedCart->getItems());
        $item = $updatedCart->getItems()->first();

        $this->assertEquals(5, $item->getQty());
        $this->assertEmpty($item->getOptionAssignments());
        $this->assertNull($item->getOptionsHash());
    }

    public function testAddItemExceedsBaseProductStock(): void
    {
        // Создаем продукт с ограниченным количеством
        $product = $this->createProduct('Limited Product', 5);

        $this->em->flush();

        // Создаем корзину
        $cart = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $this->em->persist($cart);
        $this->em->flush();

        // Пытаемся добавить больше, чем доступно
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Not enough stock for product \'Limited Product\'. Available: 5, requested: 10');

        $this->cartManager->addItem($cart, $product->getId(), 10, []);
    }

    private function createProduct(string $name, int $quantity): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setStatus(true);
        $product->setQuantity($quantity);
        $product->setPrice(1000);
        $product->setType('variable');

        $this->em->persist($product);
        return $product;
    }

    private function createOptionAssignment(
        Product $product,
        string $optionName,
        string $valueName,
        int $quantity,
        string $sku
    ): ProductOptionValueAssignment {
        // Создаем опцию
        $option = new Option();
        $option->setName($optionName);
        $option->setCode(strtolower($optionName));
        $this->em->persist($option);

        // Создаем значение опции
        $value = new OptionValue();
        $value->setValue($valueName);
        $value->setCode(strtolower($valueName));
        $this->em->persist($value);

        // Создаем назначение опции товару
        $assignment = new ProductOptionValueAssignment();
        $assignment->setProduct($product);
        $assignment->setOption($option);
        $assignment->setValue($value);
        $assignment->setQuantity($quantity);
        $assignment->setSku($sku);
        $assignment->setPrice(500); // дополнительная цена за опцию

        $this->em->persist($assignment);
        return $assignment;
    }

    private function clearDatabase(): void
    {
        // Очищаем таблицы в правильном порядке (сначала дочерние)
        $this->em->createQuery('DELETE FROM App\Entity\ProductOptionValueAssignment')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\OptionValue')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Option')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\CartItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Cart')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
    }
}
