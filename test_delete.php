<?php
// Тестовый скрипт для проверки DELETE запроса
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $path = $_SERVER['REQUEST_URI'];

    if (strpos($path, '/api/cart/items/') !== false) {
        // Симулируем успешное удаление товара
        http_response_code(204);
        exit();
    }

    // Если путь не найден
    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
    exit();
}

// Для GET запросов возвращаем тестовые данные
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (strpos($_SERVER['REQUEST_URI'], '/api/cart') !== false) {
        echo json_encode([
            'id' => 1,
            'currency' => 'RUB',
            'subtotal' => 1000,
            'discountTotal' => 0,
            'total' => 1000,
            'shipping' => [
                'method' => null,
                'cost' => 0,
                'city' => null,
                'data' => []
            ],
            'items' => [
                [
                    'id' => 25,
                    'productId' => 123,
                    'name' => 'Test Product',
                    'unitPrice' => 1000,
                    'qty' => 1,
                    'rowTotal' => 1000,
                    'optionsPriceModifier' => 0,
                    'effectiveUnitPrice' => 1000,
                    'selectedOptions' => [],
                    'optionsHash' => null
                ]
            ]
        ]);
        exit();
    }
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
