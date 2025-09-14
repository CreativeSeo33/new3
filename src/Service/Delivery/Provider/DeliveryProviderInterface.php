<?php
declare(strict_types=1);

namespace App\Service\Delivery\Provider;

use App\Service\Delivery\Dto\CalculationContext;
use App\Service\Delivery\Dto\DeliveryCalculationResult;
use App\Entity\OrderDelivery;

interface DeliveryProviderInterface
{
    public function getCode(): string;

    public function calculateWithContext(CalculationContext $context): DeliveryCalculationResult;

    /**
     * Бросает исключение при некорректных данных (напр. пустой pvzCode при pvz)
     */
    public function validate(OrderDelivery $deliveryData): void;
}
