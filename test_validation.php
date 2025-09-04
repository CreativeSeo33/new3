<?php
declare(strict_types=1);

// Тест для проверки валидации товаров
require_once 'vendor/autoload.php';

use App\Entity\Product;
use App\Entity\ProductOptionValueAssignment;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

echo "=== Тест валидации товаров ===\n\n";

// Создаем валидатор
$validator = Validation::createValidator();

// Тест 1: Вариативный товар без вариаций (должен провалиться)
echo "Тест 1: Вариативный товар без вариаций\n";
$variableProductNoVariations = new Product();
$variableProductNoVariations->setName('Тестовый вариативный товар');
$variableProductNoVariations->setType(Product::TYPE_VARIABLE);
// Для вариативного товара без вариаций просто оставляем пустую коллекцию (по умолчанию)

$violations = $validator->validate($variableProductNoVariations);
echo "Количество ошибок: " . count($violations) . "\n";
foreach ($violations as $violation) {
    echo "Ошибка: " . $violation->getMessage() . "\n";
    echo "Поле: " . $violation->getPropertyPath() . "\n";
}
echo "\n";

// Тест 2: Простой товар с вариациями (должен провалиться)
echo "Тест 2: Простой товар с вариациями\n";
$simpleProductWithVariations = new Product();
$simpleProductWithVariations->setName('Тестовый простой товар');
$simpleProductWithVariations->setType(Product::TYPE_SIMPLE);
$simpleProductWithVariations->setPrice(1000);

$assignment = new ProductOptionValueAssignment();
// Добавляем вариацию через правильный метод
$simpleProductWithVariations->addOptionAssignment($assignment);

$violations = $validator->validate($simpleProductWithVariations);
echo "Количество ошибок: " . count($violations) . "\n";
foreach ($violations as $violation) {
    echo "Ошибка: " . $violation->getMessage() . "\n";
    echo "Поле: " . $violation->getPropertyPath() . "\n";
}
echo "\n";

// Тест 3: Корректный вариативный товар (должен пройти)
echo "Тест 3: Корректный вариативный товар\n";
$correctVariableProduct = new Product();
$correctVariableProduct->setName('Корректный вариативный товар');
$correctVariableProduct->setType(Product::TYPE_VARIABLE);

// Создаем вариацию с ценой
$assignment = new ProductOptionValueAssignment();
$assignment->setPrice(1500);
$correctVariableProduct->addOptionAssignment($assignment);

$violations = $validator->validate($correctVariableProduct);
echo "Количество ошибок: " . count($violations) . "\n";
if (count($violations) === 0) {
    echo "Валидация прошла успешно!\n";
}
echo "\n";

// Тест 4: Корректный простой товар (должен пройти)
echo "Тест 4: Корректный простой товар\n";
$correctSimpleProduct = new Product();
$correctSimpleProduct->setName('Корректный простой товар');
$correctSimpleProduct->setType(Product::TYPE_SIMPLE);
$correctSimpleProduct->setPrice(1000);
// Для простого товара коллекция вариаций по умолчанию пустая

$violations = $validator->validate($correctSimpleProduct);
echo "Количество ошибок: " . count($violations) . "\n";
if (count($violations) === 0) {
    echo "Валидация прошла успешно!\n";
}
echo "\n";

// Тест 5: Проверка валидации с группами
echo "Тест 5: Валидация с группами product:create\n";
$violations = $validator->validate($variableProductNoVariations, null, ['product:create']);
echo "Количество ошибок для вариативного товара без вариаций: " . count($violations) . "\n";
foreach ($violations as $violation) {
    echo "Ошибка: " . $violation->getMessage() . "\n";
    echo "Поле: " . $violation->getPropertyPath() . "\n";
}
echo "\n";

echo "Все тесты завершены!\n";
