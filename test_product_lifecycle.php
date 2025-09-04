<?php
declare(strict_types=1);

// Простой тест для проверки логики ProductLifecycleService
require_once 'vendor/autoload.php';

use App\Entity\Product;
use App\Entity\ProductOptionValueAssignment;
use App\Service\ProductLifecycleService;
use Doctrine\Common\Collections\ArrayCollection;

// Создаем mock для ProductLifecycleService
class TestProductLifecycleService extends ProductLifecycleService {
    public function __construct() {}

    public function testMaterializeEffectivePrice(Product $product): void {
        $this->materializeEffectivePrice($product);
    }
}

// Тест 1: Простой товар
echo "=== Тест 1: Простой товар ===\n";
$simpleProduct = new Product();
$simpleProduct->setName('Простой товар');
$simpleProduct->setPrice(1000);
$simpleProduct->setSalePrice(800);
$simpleProduct->setQuantity(10);

$service = new TestProductLifecycleService();
$service->testMaterializeEffectivePrice($simpleProduct);

echo "Цена: " . $simpleProduct->getPrice() . "\n";
echo "Цена со скидкой: " . $simpleProduct->getSalePrice() . "\n";
echo "Количество: " . $simpleProduct->getQuantity() . "\n";
echo "Effective Price: " . $simpleProduct->getEffectivePrice() . "\n\n";

// Тест 2: Вариативный товар
echo "=== Тест 2: Вариативный товар ===\n";
$variableProduct = new Product();
$variableProduct->setName('Вариативный товар');
$variableProduct->setPrice(1000); // Эти значения должны быть обнулены
$variableProduct->setSalePrice(800);
$variableProduct->setQuantity(10);

// Создаем вариации с разными ценами
$assignment1 = new ProductOptionValueAssignment();
$assignment1->setPrice(1500);
$assignment1->setSalePrice(1200);

$assignment2 = new ProductOptionValueAssignment();
$assignment2->setPrice(2000);
$assignment2->setSalePrice(1800);

$assignment3 = new ProductOptionValueAssignment();
$assignment3->setPrice(800); // Самая низкая цена

$variableProduct->addOptionAssignment($assignment1);
$variableProduct->addOptionAssignment($assignment2);
$variableProduct->addOptionAssignment($assignment3);

$service->testMaterializeEffectivePrice($variableProduct);

echo "Цена: " . $variableProduct->getPrice() . " (должна быть null)\n";
echo "Цена со скидкой: " . $variableProduct->getSalePrice() . " (должна быть null)\n";
echo "Количество: " . $variableProduct->getQuantity() . " (должно быть null)\n";
echo "Effective Price: " . $variableProduct->getEffectivePrice() . " (должна быть 800 - минимальная цена)\n\n";

echo "Тесты завершены!\n";
