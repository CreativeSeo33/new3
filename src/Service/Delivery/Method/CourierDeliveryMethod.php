<?php
declare(strict_types=1);

namespace App\Service\Delivery\Method;

use App\Entity\Cart;
use App\Entity\PvzPrice;
use App\Service\Delivery\Dto\DeliveryCalculationResult;
use App\Service\Delivery\Provider\DeliveryProviderInterface;
use App\Service\Delivery\Dto\CalculationContext;
use App\Exception\InvalidDeliveryDataException;

final class CourierDeliveryMethod implements DeliveryMethodInterface, DeliveryProviderInterface
{
    private const METHOD_CODE = 'courier';

    public function __construct(
        private readonly string $calculationType, // Внедряется из services.yaml
        private readonly int $surcharge, // Наценка за курьерскую доставку из конфигурации
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
        $effectiveFreeThreshold = ($freeDeliveryThreshold !== null && $freeDeliveryThreshold > 0)
            ? $freeDeliveryThreshold
            : $this->defaultFreeThreshold;
        $baseCost = $city->getCost();

        // 1. Проверка на бесплатную доставку (правило общее)
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

        // 2. Логика расчета базовой стоимости
        $baseTotalCost = 0;
        if ($this->getCalculationType() === self::TYPE_COST_PER_ITEM) {
            // Расчет "за единицу товара"
            $baseTotalCost = $baseCost * $cart->getTotalItemQuantity();
        } else {
            // Расчет по фиксированной ставке
            $baseTotalCost = $baseCost;
        }

        $totalCost = $baseTotalCost + $this->surcharge;

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
                'effectiveFreeThreshold' => $effectiveFreeThreshold,
                'defaultFreeThreshold' => $this->defaultFreeThreshold,
                'cartSubtotal' => $cart->getSubtotal(),
                'itemsQty' => $cart->getTotalItemQuantity(),
                'surcharge' => $this->surcharge,
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
        $address = trim((string)($deliveryData->getAddress() ?? ''));
        if ($address === '' || mb_strlen($address) > 255) {
            throw new InvalidDeliveryDataException('Неверный адрес доставки');
        }
    }
}
