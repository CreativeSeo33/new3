<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Service\Delivery\DeliveryService;
use App\Service\PriceNormalizer;

final class CartCalculator
{
    public function __construct(private DeliveryService $delivery) {}

    public function recalculate(Cart $cart): void
    {
        $subtotal = 0;

        foreach ($cart->getItems() as $item) {
            // Определяем эффективную цену с учетом опций
            $effectiveUnitPrice = $this->calculateEffectivePrice($item);

            // Сохраняем эффективную цену
            $item->setEffectiveUnitPrice($effectiveUnitPrice);

            // Вычисляем итоговую сумму по строке
            $rowTotal = $effectiveUnitPrice * $item->getQty();

            // Сохраняем вычисленную сумму
            $item->setRowTotal($rowTotal);

            // Добавляем к общей сумме
            $subtotal += $rowTotal;
        }

        $discountTotal = 0;
        $shippingCost = 0;
        
        if ($cart->getShippingMethod()) {
            // Используем новый сервис. Он вернет 0, если расчет невозможен.
            $shippingCost = $this->delivery->quote($cart);
            $cart->setShippingCost($shippingCost);
        }
        
        $total = max(0, $subtotal - $discountTotal + $shippingCost);

        $cart->setSubtotal($subtotal);
        $cart->setDiscountTotal($discountTotal);
        $cart->setTotal($total);
    }
    
    /**
     * Вычисление эффективной цены товара
     *
     * Единая модель ценообразования: базовая цена + модификатор опций
     * Это гарантирует консистентность с логикой CartManager
     */
    private function calculateEffectivePrice(CartItem $item): int
    {
        // Единая модель: базовая цена + модификатор опций
        return $item->getUnitPrice() + $item->getOptionsPriceModifier();
    }
}


