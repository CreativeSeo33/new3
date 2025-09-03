<?php

$conn = new mysqli('localhost', 'root', '', 'new3');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "Checking cart_item table:\n";
$result = $conn->query('SELECT id, cart_id, product_id, options_hash, qty FROM cart_item WHERE cart_id = 28');
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Cart: {$row['cart_id']}, Product: {$row['product_id']}, Hash: {$row['options_hash']}, Qty: {$row['qty']}\n";
}

echo "\nChecking unique index:\n";
$result2 = $conn->query("SHOW INDEX FROM cart_item WHERE Key_name = 'uniq_cart_product_options'");
if ($result2->num_rows > 0) {
    echo "Unique index exists\n";
    while ($row = $result2->fetch_assoc()) {
        echo "  Column: {$row['Column_name']}\n";
    }
} else {
    echo "Unique index does NOT exist\n";
}

echo "\nTesting options hash generation:\n";
function generateOptionsHash(array $optionAssignmentIds): string {
    sort($optionAssignmentIds);
    return md5(implode(',', $optionAssignmentIds));
}

// Проверяем хеши для существующих товаров
$hashes = [
    '028ae2a0fad1e1c2f27acb6380662438' => [36, 38],
    '3559a41492acd3c7d74bf41994b6da99' => [37, 38],
];

foreach ($hashes as $hash => $ids) {
    $generated = generateOptionsHash($ids);
    echo "Expected: $hash, Generated: $generated, Match: " . ($hash === $generated ? 'YES' : 'NO') . "\n";
}

$conn->close();
