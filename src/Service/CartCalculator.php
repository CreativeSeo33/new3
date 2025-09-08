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

    public function recalculate(Cart $cart): void
    {
        $subtotal = 0;
        $policy = $cart->getPricingPolicy();

        if ($policy === 'SNAPSHOT') {
            // SNAPSHOT: используем зафиксированные цены, не обращаемся к каталогу
            foreach ($cart->getItems() as $item) {
                $rowTotal = $item->getEffectiveUnitPrice() * $item->getQty();
                $item->setRowTotal($rowTotal);
                $subtotal += $rowTotal;
            }
        } else {
            // LIVE: вычисляем актуальные цены на лету, но не перезаписываем снепшот
            foreach ($cart->getItems() as $item) {
                $liveEffectiveUnitPrice = $this->livePrice->effectiveUnitPriceLive($item);
                $rowTotal = $liveEffectiveUnitPrice * $item->getQty();
                // Не перезаписываем снепшот-поля позиции
                $subtotal += $rowTotal;
            }
        }

        $discountTotal = 0;
        $shippingCost = 0;

        if ($cart->getShippingMethod()) {
            $shippingCost = $this->delivery->quote($cart);
            $cart->setShippingCost($shippingCost);
        }

        $total = max(0, $subtotal - $discountTotal + $shippingCost);

        $cart->setSubtotal($subtotal);
        $cart->setDiscountTotal($discountTotal);
        $cart->setTotal($total);
    }
}


