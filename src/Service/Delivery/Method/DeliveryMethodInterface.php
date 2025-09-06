<?php
declare(strict_types=1);

namespace App\Service\Delivery\Method;

use App\Entity\Cart;
use App\Entity\PvzPrice;
use App\Service\Delivery\Dto\DeliveryCalculationResult;

/**
 * Интерфейс для всех стратегий (методов) расчета доставки.
 */
interface DeliveryMethodInterface
{
    public const TYPE_COST_PER_ITEM = 'cost_per_item'; // За единицу товара
    public const TYPE_FLAT_RATE = 'flat_rate';         // Фиксированная ставка

    /**
     * Проверяет, поддерживает ли данный метод указанный код (например, 'pvz').
     */
    public function supports(string $methodCode): bool;

    /**
     * Рассчитывает стоимость доставки для указанной корзины и города.
     */
    public function calculate(Cart $cart, PvzPrice $city): DeliveryCalculationResult;

    /**
     * Возвращает уникальный код метода.
     */
    public function getCode(): string;

    /**
     * Возвращает человекочитаемое название метода для отображения в UI.
     */
    public function getLabel(): string;

    /**
     * Возвращает тип расчета стоимости (например, 'cost_per_item' или 'flat_rate').
     */
    public function getCalculationType(): string;
}
