<?php

// Скрипт для очистки сессии корзины
session_start();

// Ключи сессии корзины
$cartKeys = [
    'cart.token',
    'cart.id',
    'cart.items',
    'cart.updated_at'
];

// Очищаем ключи корзины
foreach ($cartKeys as $key) {
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
        echo "Removed: $key\n";
    }
}

// Также очищаем из checkout если есть
if (isset($_SESSION['checkout']['cart'])) {
    unset($_SESSION['checkout']['cart']);
    echo "Removed: checkout.cart\n";
}

echo "Session cleanup completed!\n";
