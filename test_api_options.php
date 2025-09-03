<?php

// Тестирование API корзины с опциями товара
$data = json_encode([
    'productId' => 1,
    'qty' => 2,
    'optionAssignmentIds' => [1, 2] // Пример ID опций
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'X-Requested-With: XMLHttpRequest'
        ],
        'content' => $data
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$result = file_get_contents('https://127.0.0.1:8001/api/cart/items', false, $context);

if ($result === false) {
    echo "Error: " . error_get_last()['message'] . "\n";
} else {
    echo "Success with options: " . $result . "\n";
}
