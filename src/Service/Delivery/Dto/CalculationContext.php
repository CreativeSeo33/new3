<?php
declare(strict_types=1);

namespace App\Service\Delivery\Dto;

use App\Entity\Cart;
use App\Entity\PvzPrice;

final class CalculationContext
{
    public function __construct(
        public readonly Cart $cart,
        public readonly ?PvzPrice $city,   // null, если город не найден/не задан
        public readonly array $options = [] // ['pickupPointId' => ..., 'address' => ..., 'zip' => ..., ...]
    ) {}
}
