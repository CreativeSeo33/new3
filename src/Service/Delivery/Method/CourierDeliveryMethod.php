<?php
declare(strict_types=1);

namespace App\Service\Delivery\Method;

use App\Entity\Cart;
use App\Entity\PvzPrice;
use App\Service\Delivery\Dto\DeliveryCalculationResult;

final class CourierDeliveryMethod implements DeliveryMethodInterface
{
    private const METHOD_CODE = 'courier';
    private const SURCHARGE = 300; // Наценка за курьерскую доставку

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
        return 'Доставка курьером';
    }

    public function getCalculationType(): string
    {
        return $this->calculationType;
    }

    public function calculate(Cart $cart, PvzPrice $city): DeliveryCalculationResult
    {
        $term = $city->getSrok() ?? 'Срок не указан';
        $freeDeliveryThreshold = $city->getFree();

        // 1. Проверка на бесплатную доставку (правило общее)
        if ($freeDeliveryThreshold !== null && $freeDeliveryThreshold > 0 && $cart->getSubtotal() >= $freeDeliveryThreshold) {
            return new DeliveryCalculationResult(
                cost: 0,
                term: $term,
                message: 'Бесплатно',
                isFree: true
            );
        }

        $baseCost = $city->getCost();
        if ($baseCost === null) {
            return new DeliveryCalculationResult(
                cost: null,
                term: '',
                message: 'Расчет менеджером',
                requiresManagerCalculation: true
            );
        }

        // 2. Логика расчета базовой стоимости
        $baseTotalCost = 0;
        if ($this->getCalculationType() === self::TYPE_COST_PER_ITEM) {
            // Расчет "за единицу товара"
            $baseTotalCost = $baseCost * $cart->getTotalItemQuantity();
        } else {
            // Расчет по фиксированной ставке
            $baseTotalCost = $baseCost;
        }

        $totalCost = $baseTotalCost + self::SURCHARGE;

        return new DeliveryCalculationResult(
            cost: $totalCost,
            term: $term
        );
    }
}
