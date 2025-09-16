<?php
declare(strict_types=1);

namespace App\Service\Delivery\Method;

use App\Entity\Cart;
use App\Entity\PvzPrice;
use App\Service\Delivery\Dto\DeliveryCalculationResult;
use App\Service\Delivery\Provider\DeliveryProviderInterface;
use App\Service\Delivery\Dto\CalculationContext;
use App\Exception\InvalidDeliveryDataException;

final class PvzDeliveryMethod implements DeliveryMethodInterface, DeliveryProviderInterface
{
    /**
     * AI-META v1
     * role: Метод доставки ПВЗ; расчёт тарифов и валидация данных для OrderDelivery
     * module: Delivery
     * dependsOn:
     *   - App\Service\Delivery\Dto\CalculationContext
     *   - App\Entity\PvzPrice
     * invariants:
     *   - Порог бесплатной доставки берётся из БД либо из конфигурации по умолчанию
     *   - traceData фиксирует источник и параметры расчёта
     * transaction: none
     * lastUpdated: 2025-09-15
     */
    private const METHOD_CODE = 'pvz';

    public function __construct(
        private readonly string $calculationType, // Внедряется из services.yaml
        private readonly int $defaultFreeThreshold
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
        $freeDeliveryThreshold = $city->getFree(); // Порог бесплатной доставки из БД (может быть null)
        $effectiveFreeThreshold = ($freeDeliveryThreshold !== null && $freeDeliveryThreshold > 0)
            ? $freeDeliveryThreshold
            : 0; // 0 и NULL означает, что бесплатной доставки нет
        $baseCost = $city->getCost();

        // 1. Проверка на бесплатную доставку по сумме заказа
        if ($effectiveFreeThreshold > 0 && $cart->getSubtotal() >= $effectiveFreeThreshold) {
            return new DeliveryCalculationResult(
                cost: 0,
                term: $term,
                message: 'Бесплатно',
                isFree: true,
                requiresManagerCalculation: false,
                estimatedDate: null,
                traceData: [
                    'source' => 'pvz_price',
                    'method' => self::METHOD_CODE,
                    'calculationType' => $this->getCalculationType(),
                    'baseCost' => $baseCost,
                    'freeThreshold' => $freeDeliveryThreshold,
                    'effectiveFreeThreshold' => $effectiveFreeThreshold,
                    'defaultFreeThreshold' => $this->defaultFreeThreshold,
                    'cartSubtotal' => $cart->getSubtotal(),
                    'itemsQty' => $cart->getTotalItemQuantity(),
                    'reason' => 'free_threshold',
                    'effectiveCost' => 0,
                ]
            );
        }
        if ($baseCost === null) {
            // Если для города не указана стоимость
            return new DeliveryCalculationResult(
                cost: null,
                term: '',
                message: 'Расчет менеджером',
                requiresManagerCalculation: true,
                estimatedDate: null,
                traceData: [
                    'source' => 'custom',
                    'method' => self::METHOD_CODE,
                    'calculationType' => $this->getCalculationType(),
                    'baseCost' => null,
                    'freeThreshold' => $freeDeliveryThreshold,
                    'cartSubtotal' => $cart->getSubtotal(),
                    'itemsQty' => $cart->getTotalItemQuantity(),
                    'reason' => 'no_base_cost',
                    'effectiveCost' => null,
                ]
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
            cost: $totalCost,
            term: $term,
            message: null,
            isFree: false,
            requiresManagerCalculation: false,
            estimatedDate: null,
            traceData: [
                'source' => 'pvz_price',
                'method' => self::METHOD_CODE,
                'calculationType' => $this->getCalculationType(),
                'baseCost' => $baseCost,
                'freeThreshold' => $freeDeliveryThreshold,
                'cartSubtotal' => $cart->getSubtotal(),
                'itemsQty' => $cart->getTotalItemQuantity(),
                'effectiveCost' => $totalCost,
            ]
        );
    }

    public function calculateWithContext(CalculationContext $context): DeliveryCalculationResult
    {
        if ($context->city === null) {
            return new DeliveryCalculationResult(null, '', 'Город не определен', false, true);
        }
        return $this->calculate($context->cart, $context->city);
    }

    public function validate(\App\Entity\OrderDelivery $deliveryData): void
    {
        $pvzCode = trim((string)($deliveryData->getPvzCode() ?? ''));
        if ($pvzCode === '') {
            throw new InvalidDeliveryDataException('Требуется выбрать пункт выдачи');
        }
        // при необходимости: проверить соответствие города/ПВЗ через репозиторий в CheckoutController до сохранения
    }
}
