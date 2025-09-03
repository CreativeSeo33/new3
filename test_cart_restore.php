<?php

// Тестирование логики проверки снимка корзины
function hasValidItems(array $snapshot): bool {
    foreach ($snapshot as $itemData) {
        $productId = $itemData['productId'] ?? $itemData['product_id'] ?? null;
        if ($productId && ($itemData['qty'] ?? 0) > 0) {
            return true;
        }
    }
    return false;
}

// Тестовые случаи
$testCases = [
    'empty_snapshot' => [],
    'snapshot_with_zero_qty' => [
        ['productId' => 5, 'qty' => 0, 'options' => []]
    ],
    'snapshot_with_valid_items' => [
        ['productId' => 5, 'qty' => 1, 'options' => []],
        ['productId' => 7, 'qty' => 2, 'options' => []]
    ],
    'mixed_snapshot' => [
        ['productId' => 5, 'qty' => 0, 'options' => []],
        ['productId' => 7, 'qty' => 1, 'options' => []]
    ]
];

echo "Testing cart snapshot validation:\n\n";

foreach ($testCases as $name => $snapshot) {
    $hasValid = hasValidItems($snapshot);
    echo "Test '$name': " . ($hasValid ? 'HAS VALID ITEMS' : 'NO VALID ITEMS') . "\n";
    echo "  Snapshot: " . json_encode($snapshot) . "\n";
    echo "  Should restore: " . ($hasValid ? 'YES' : 'NO') . "\n\n";
}
