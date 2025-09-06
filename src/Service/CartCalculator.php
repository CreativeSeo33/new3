<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Service\Delivery\DeliveryService;

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
     * Если есть опции - суммируем цены всех опций
     * Если опций нет - берем базовую цену товара
     */
    private function calculateEffectivePrice(CartItem $item): int
    {
        $optionAssignments = $item->getOptionAssignments();
        $optionsPrice = 0;

        // Если есть опции, суммируем цены всех опций
        if (!$optionAssignments->isEmpty()) {
            foreach ($optionAssignments as $option) {
                // Приоритет у sale_price если есть
                if ($option->getSalePrice() !== null && $option->getSalePrice() > 0) {
                    $optionPrice = $option->getSalePrice();
                } else {
                    // Иначе обычная цена опции
                    $optionPrice = $option->getPrice() ?? 0;
                }
                $optionsPrice += $optionPrice;
            }

            // Если сумма цен опций больше 0, используем её
            if ($optionsPrice > 0) {
                return $optionsPrice;
            }
        }

        // Если опций нет или у них нет цены - используем базовую цену товара
        return $item->getUnitPrice();
    }
}


