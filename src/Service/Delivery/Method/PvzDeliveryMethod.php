<?php
declare(strict_types=1);

namespace App\Service\Delivery\Method;

use App\Entity\Cart;
use App\Entity\PvzPrice;
use App\Service\Delivery\Dto\DeliveryCalculationResult;

final class PvzDeliveryMethod implements DeliveryMethodInterface
{
    private const METHOD_CODE = 'pvz';

    public function __construct(
        private readonly string $calculationType // Внедряется из services.yaml
    ) {}

    public function supports(string $methodCode): bool
    {
        return $methodCode === self::METHOD_CODE;
    }

    public function getCode(): string
    {
        return self::METHOD_CODE;
    }

    public function getLabel(): string
    {
        return 'Доставка в пункт выдачи';
    }

    public function getCalculationType(): string
    {
        return $this->calculationType;
    }

    public function calculate(Cart $cart, PvzPrice $city): DeliveryCalculationResult
    {
        $term = $city->getSrok() ?? 'Срок не указан';
        $freeDeliveryThreshold = $city->getFree(); // Порог бесплатной доставки

        // 1. Проверка на бесплатную доставку по сумме заказа
        if ($freeDeliveryThreshold !== null && $freeDeliveryThreshold > 0 && $cart->getSubtotal() >= $freeDeliveryThreshold) {
            return new DeliveryCalculationResult(
                cost: 0.0,
                term: $term,
                message: 'Бесплатно',
                isFree: true
            );
        }

        $baseCost = $city->getCost();
        if ($baseCost === null) {
            // Если для города не указана стоимость
            return new DeliveryCalculationResult(
                cost: null,
                term: '',
                message: 'Расчет менеджером',
                requiresManagerCalculation: true
            );
        }

        // 2. Логика расчета стоимости с учетом типа
        $totalCost = 0;
        if ($this->getCalculationType() === self::TYPE_COST_PER_ITEM) {
            // Расчет "за единицу товара"
            $totalCost = $baseCost * $cart->getTotalItemQuantity();
        } else {
            // Расчет по фиксированной ставке (TYPE_FLAT_RATE)
            $totalCost = $baseCost;
        }

        return new DeliveryCalculationResult(
            cost: (float) $totalCost,
            term: $term
        );
    }
}
