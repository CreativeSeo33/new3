<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CartSessionStorage
{
    private const SESSION_KEY = 'cart';
    private const CART_TOKEN_KEY = 'cart.token';
    private const CART_ID_KEY = 'cart.id';
    private const CART_ITEMS_KEY = 'cart.items';
    private const CART_UPDATED_KEY = 'cart.updated_at';
    
    public function __construct(
        private RequestStack $requestStack
    ) {}
    
    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
    
    /**
     * Сохранение минимальных данных корзины в сессию
     */
    public function saveCartReference(int $cartId, string $token): void
    {
        $session = $this->getSession();
        
        // Сохраняем в структуре, совместимой с checkout
        $session->set('checkout', array_merge(
            $session->get('checkout', []),
            [
                'cart' => [
                    'token' => $token,
                    'id' => $cartId,
                ]
            ]
        ));
        
        // Дублируем для быстрого доступа
        $session->set(self::CART_TOKEN_KEY, $token);
        $session->set(self::CART_ID_KEY, $cartId);
    }
    
    /**
     * Сохранение снимка товаров для гостя (fallback если БД недоступна)
     */
    public function saveCartSnapshot(array $items): void
    {
        $session = $this->getSession();
        
        $snapshot = [
            'items' => array_map(fn($item) => [
                'product_id' => $item['productId'],
                'name' => $item['name'],
                'qty' => $item['qty'],
                'price' => $item['price'],
                'options' => $item['options'] ?? [],
                'options_hash' => $item['optionsHash'] ?? null,
            ], $items),
            'updated_at' => time(),
        ];
        
        $session->set(self::CART_ITEMS_KEY, $snapshot['items']);
        $session->set(self::CART_UPDATED_KEY, $snapshot['updated_at']);
    }
    
    /**
     * Получение токена корзины из сессии
     */
    public function getCartToken(): ?string
    {
        // Сначала проверяем в checkout (приоритет)
        $checkout = $this->getSession()->get('checkout', []);
        if (isset($checkout['cart']['token'])) {
            return $checkout['cart']['token'];
        }
        
        // Fallback на прямое значение
        return $this->getSession()->get(self::CART_TOKEN_KEY);
    }
    
    /**
     * Получение ID корзины из сессии
     */
    public function getCartId(): ?int
    {
        $checkout = $this->getSession()->get('checkout', []);
        if (isset($checkout['cart']['id'])) {
            return (int) $checkout['cart']['id'];
        }
        
        $id = $this->getSession()->get(self::CART_ID_KEY);
        return $id ? (int) $id : null;
    }
    
    /**
     * Получение снимка товаров из сессии
     */
    public function getCartSnapshot(): array
    {
        return $this->getSession()->get(self::CART_ITEMS_KEY, []);
    }
    
    /**
     * Очистка данных корзины из сессии
     */
    public function clearCart(): void
    {
        $session = $this->getSession();
        
        // Очищаем из checkout
        $checkout = $session->get('checkout', []);
        unset($checkout['cart']);
        $session->set('checkout', $checkout);
        
        // Очищаем прямые ключи
        $session->remove(self::CART_TOKEN_KEY);
        $session->remove(self::CART_ID_KEY);
        $session->remove(self::CART_ITEMS_KEY);
        $session->remove(self::CART_UPDATED_KEY);
    }
    
    /**
     * Миграция корзины при логине пользователя
     */
    public function migrateToUser(int $userId): void
    {
        // При логине очищаем сессионные данные,
        // так как корзина теперь привязана к пользователю
        $this->clearCart();
    }
}
