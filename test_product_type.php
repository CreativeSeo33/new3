<?php
declare(strict_types=1);

// Тест для проверки работы с полем type
require_once 'vendor/autoload.php';

use App\Entity\Product;
use App\Entity\ProductOptionValueAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\DriverManager;

$connection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'new3',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
]);

$entityManager = new \Doctrine\ORM\EntityManager(
    $connection,
    new \Doctrine\ORM\Configuration()
);

// Тест создания простого товара
echo "=== Тест создания простого товара ===\n";
$simpleProduct = new Product();
$simpleProduct->setName('Тестовый простой товар');
$simpleProduct->setPrice(1000);
$simpleProduct->setSalePrice(800);
$simpleProduct->setType(Product::TYPE_SIMPLE);
$simpleProduct->setStatus(true);
$simpleProduct->setQuantity(10);

echo "Тип товара: " . $simpleProduct->getType() . "\n";
echo "Это простой товар: " . ($simpleProduct->isSimple() ? 'Да' : 'Нет') . "\n";
echo "Это вариативный товар: " . ($simpleProduct->isVariable() ? 'Да' : 'Нет') . "\n\n";

// Тест создания вариативного товара
echo "=== Тест создания вариативного товара ===\n";
$variableProduct = new Product();
$variableProduct->setName('Тестовый вариативный товар');
$variableProduct->setType(Product::TYPE_VARIABLE);
$variableProduct->setStatus(true);

// Создаем вариации
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

echo "Тип товара: " . $variableProduct->getType() . "\n";
echo "Это простой товар: " . ($variableProduct->isSimple() ? 'Да' : 'Нет') . "\n";
echo "Это вариативный товар: " . ($variableProduct->isVariable() ? 'Да' : 'Нет') . "\n";
echo "Количество вариаций: " . $variableProduct->getOptionAssignments()->count() . "\n\n";

echo "Тесты завершены!\n";
