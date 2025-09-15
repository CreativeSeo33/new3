<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Service\Delivery\DeliveryService;
use App\Service\PriceNormalizer;

final class CartCalculator
{
    /**
     * AI-META v1
     * role: Пересчёт сумм корзины; быстрый totals-only и тяжёлый пересчёт доставки/скидок
     * module: Cart
     * dependsOn:
     *   - App\Service\Delivery\DeliveryService
     *   - App\Service\LivePriceCalculator
     * invariants:
     *   - recalculateTotalsOnly не выполняет внешних IO и используется под локом
     *   - Полный пересчёт доставки выполняется вне критической секции
     * transaction: none
     * tests:
     *   - tests/Service/PriceNormalizerTest.php
     * lastUpdated: 2025-09-15
     */
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
                $expectedRowTotal = $effectivePrice * $item->getQty();
                $currentRowTotal = $item->getRowTotal();

                // Проверяем, соответствует ли текущий rowTotal ожидаемому значению
                // Если нет, то пересчитываем (для товаров, добавленных давно или с устаревшими ценами)
                if (abs($expectedRowTotal - $currentRowTotal) > 1) { // Допускаем погрешность в 1 копейку
                    $item->setRowTotal($expectedRowTotal);
                }

                $subtotal += $item->getRowTotal(); // Используем актуальное значение rowTotal
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

        // Изменена логика: total = subtotal (доставка добавляется отдельно в шаблоне)
        $total = max(0, $cart->getSubtotal() - $discountTotal);

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


