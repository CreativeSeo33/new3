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
        $optionAssignments = $item->getOptionAssignments();

        // Новая логика: если есть опции, используем максимальную цену из опций
        if (!$optionAssignments->isEmpty()) {
            $optionPrices = [];
            foreach ($optionAssignments as $assignment) {
                $price = $assignment->getSalePrice() ?? $assignment->getPrice() ?? 0;
                if ($price > 0) {
                    $optionPrices[] = PriceNormalizer::toRubInt($price);
                }
            }

            if (!empty($optionPrices)) {
                return max($optionPrices);
            }
        }

        // Если опций нет или они без цены, используем базовую цену товара
        return PriceNormalizer::toRubInt($product->getEffectivePrice() ?? $product->getPrice() ?? 0);
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
