<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\CartItem;
use App\Entity\ProductOptionValueAssignment;

/**
 * LivePriceCalculator - сервис для расчёта актуальных цен товаров в корзине
 *
 * Используется в LIVE-режиме ценообразования для получения текущих цен из каталога
 */
final class LivePriceCalculator
{
    /**
     * Вычисляет эффективную цену позиции на основе текущих данных из каталога
     */
    public function effectiveUnitPriceLive(CartItem $item): int
    {
        $product = $item->getProduct();

        // Базовая цена из продукта (текущая)
        $basePriceRub = $product->getEffectivePrice() ?? $product->getPrice() ?? 0;

        // Применяем логику опций
        $setPrices = [];
        $modifier = 0;

        foreach ($item->getOptionAssignments() as $assignment) {
            $price = $assignment->getSalePrice() ?? $assignment->getPrice() ?? 0;

            if ($assignment->getSetPrice() === true && $price > 0) {
                $setPrices[] = $price;
            } else {
                $modifier += $price;
            }
        }

        // Если есть опции с setPrice, берём максимум из них как базовую цену
        $unitPrice = !empty($setPrices) ? max($setPrices) : $basePriceRub;

        return $unitPrice + $modifier;
    }

    /**
     * Проверяет, изменилась ли цена позиции по сравнению со снепшотом
     */
    public function hasPriceChanged(CartItem $item): bool
    {
        $livePrice = $this->effectiveUnitPriceLive($item);
        return $livePrice !== $item->getEffectiveUnitPrice();
    }
}
