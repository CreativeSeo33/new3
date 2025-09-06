<?php
declare(strict_types=1);

require_once 'vendor/autoload.php';

use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

$kernel = new App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get(EntityManagerInterface::class);
$cartRepo = $container->get(CartRepository::class);

// Получаем cart_id из базы данных
$result = $em->getConnection()->executeQuery("SELECT cart_id FROM cart_item WHERE id = 80");
$cartId = $result->fetchOne();

if (!$cartId) {
    echo "Cart item 80 not found\n";
    exit(1);
}

echo "Cart ID: $cartId\n";

// Преобразуем в ULID
try {
    $ulid = \Symfony\Component\Uid\Ulid::fromString($cartId);
    $cart = $cartRepo->findActiveById($ulid);

    if (!$cart) {
        echo "Cart not found\n";
        exit(1);
    }

    echo "Found cart with " . $cart->getItems()->count() . " items\n";

    // Пытаемся найти товар напрямую
    $item = $cartRepo->findItemByIdForUpdate($cart, 80);

    if ($item) {
        echo "Found item ID 80, removing...\n";
        $em->remove($item);
        $em->flush();
        echo "Item removed and flushed!\n";

        // Проверяем, удален ли товар
        $count = $em->getConnection()->executeQuery("SELECT COUNT(*) FROM cart_item WHERE id = 80")->fetchOne();
        echo "Items with ID 80 remaining: $count\n";
    } else {
        echo "Item ID 80 not found in cart\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$kernel->shutdown();
