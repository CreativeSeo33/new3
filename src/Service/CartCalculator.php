<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Service\Delivery\DeliveryService;
use App\Service\PriceNormalizer;

final class CartCalculator
{
    public function __construct(
        private DeliveryService $delivery,
        private LivePriceCalculator $livePrice
    ) {}

    /**
     * Быстрый пересчет только итоговых сумм позиций без внешних IO
     * Используется в критической секции под блокировкой
     */
    public function recalculateTotalsOnly(Cart $cart): void
    {
        $subtotal = 0;
        $policy = $cart->getPricingPolicy();

        if ($policy === 'SNAPSHOT') {
            // SNAPSHOT: используем зафиксированные цены
            foreach ($cart->getItems() as $item) {
                $rowTotal = $item->getEffectiveUnitPrice() * $item->getQty();
                $item->setRowTotal($rowTotal);
                $subtotal += $rowTotal;
            }
        } else {
            // LIVE: используем актуальные цены, но только из уже рассчитанных данных
            // (предполагается, что live цены уже были рассчитаны ранее)
            foreach ($cart->getItems() as $item) {
                $effectivePrice = $item->getEffectiveUnitPrice(); // Используем кэшированное значение
                $rowTotal = $effectivePrice * $item->getQty();
                $item->setRowTotal($rowTotal);
                $subtotal += $rowTotal;
            }
        }

        $cart->setSubtotal($subtotal);
        // total пока не трогаем - его доуточним после доставки/скидок
    }

    /**
     * Пересчет доставки и скидок с внешними IO
     * Выполняется вне критической секции
     */
    public function recalculateShippingAndDiscounts(Cart $cart): void
    {
        $discountTotal = 0;
        $shippingCost = 0;

        if ($cart->getShippingMethod()) {
            $shippingCost = $this->delivery->quote($cart); // Может быть медленным
            $cart->setShippingCost($shippingCost);
        }

        $total = max(0, $cart->getSubtotal() - $discountTotal + $shippingCost);

        $cart->setDiscountTotal($discountTotal);
        $cart->setTotal($total);
    }

    /**
     * Полный пересчет (для обратной совместимости)
     * @deprecated Используйте recalculateTotalsOnly + recalculateShippingAndDiscounts
     */
    public function recalculate(Cart $cart): void
    {
        $this->recalculateTotalsOnly($cart);
        $this->recalculateShippingAndDiscounts($cart);
    }
}


