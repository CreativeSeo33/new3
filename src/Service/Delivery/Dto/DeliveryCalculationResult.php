<?php
declare(strict_types=1);

namespace App\Service\Delivery\Dto;

/**
 * DTO для хранения результата расчета стоимости доставки.
 * Использование readonly-свойств гарантирует неизменяемость объекта после создания.
 */
final class DeliveryCalculationResult
{
    public function __construct(
        // Рассчитанная стоимость. null, если требуется расчет менеджером.
        public readonly ?int $cost,
        // Срок доставки (например, "2-3 дня")
        public readonly string $term,
        // Специальное сообщение для клиента (например, "Бесплатно" или "Расчет менеджером")
        public readonly ?string $message = null,
        // Флаг бесплатной доставки
        public readonly bool $isFree = false,
        // Флаг, указывающий на необходимость ручного расчета
        public readonly bool $requiresManagerCalculation = false
    ) {}
}
