<?php
declare(strict_types=1);

namespace App\Service\Delivery;

use App\Entity\Cart;
use App\Repository\PvzPriceRepository;
use App\Service\Delivery\Dto\DeliveryCalculationResult;
use App\Service\Delivery\Method\DeliveryMethodInterface;
use App\Service\DeliveryContext;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Основной сервис для управления расчетами доставки.
 * Заменяет старый ShippingCalculator.
 */
final class DeliveryService
{
    private const MANAGER_CALCULATION_MESSAGE = 'Расчет менеджером';

    /** @var array<string, DeliveryMethodInterface> */
    private array $methods = [];

    public function __construct(
        #[TaggedIterator('app.delivery_method')] iterable $deliveryMethods,
        private readonly DeliveryContext $deliveryContext,
        private readonly PvzPriceRepository $pvzPriceRepository,
        private readonly CacheItemPoolInterface $cache
    ) {
        // Преобразуем итератор в индексированный по коду массив для быстрого доступа
        foreach ($deliveryMethods as $method) {
            $this->methods[$method->getCode()] = $method;
        }
    }

    /**
     * Основной метод для расчета стоимости доставки для корзины.
     * Возвращает детальный DTO с результатом.
     */
    public function calculateForCart(Cart $cart): DeliveryCalculationResult
    {
        // 1. Получаем контекст доставки из сессии
        $context = $this->deliveryContext->get();
        $cityName = $context['cityName'] ?? null;
        $methodCode = $context['methodCode'] ?? 'pvz'; // По умолчанию 'pvz'

        // 2. Если город не определен, расчет невозможен
        if (!$cityName) {
            return new DeliveryCalculationResult(null, '', 'Город не определен', false, true);
        }

        // 3. Ищем данные по городу в БД (нормализованный поиск)
        $city = $this->pvzPriceRepository->findOneByCityNormalized($cityName);
        if (!$city) {
            return new DeliveryCalculationResult(null, '', self::MANAGER_CALCULATION_MESSAGE, false, true);
        }

        // 4. Находим подходящую стратегию (метод)
        $method = $this->methods[$methodCode] ?? null;
        if (!$method) {
             // Можно бросить исключение или вернуть ошибку
            return new DeliveryCalculationResult(null, '', "Метод '{$methodCode}' не найден", false, true);
        }

        // 5. Если метод поддерживает контекстный расчет — используем его
        if ($method instanceof \App\Service\Delivery\Provider\DeliveryProviderInterface) {
            $ctxArr = $this->deliveryContext->get();
            $options = array_intersect_key($ctxArr, array_flip(['pickupPointId','address','zip','etaDays']));
            $calcCtx = new \App\Service\Delivery\Dto\CalculationContext($cart, $city, $options);
            return $method->calculateWithContext($calcCtx);
        }

        // Fallback: старый путь
        return $method->calculate($cart, $city);
    }

    /**
     * Возвращает стоимость доставки как целое число для обратной совместимости.
     */
    public function quote(Cart $cart): int
    {
        $context = $this->deliveryContext->get();
        $city = $context['cityName'] ?? '';
        $method = $context['methodCode'] ?? 'pvz';
        $qty = $cart->getTotalItemQuantity();

        $key = 'ship_quote_' . md5($city . '|' . $method . '|' . $qty);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return (int)$item->get();
        }

        $result = $this->calculateForCart($cart);
        $cost = (int)($result->cost ?? 0);

        $item->set($cost)->expiresAfter(60); // 60 сек
        $this->cache->save($item);

        return $cost;
    }

    /**
     * Возвращает список всех доступных методов доставки.
     * @return array<int, array{code: string, label: string}>
     */
    public function getAvailableMethods(): array
    {
        $availableMethods = [];
        foreach ($this->methods as $method) {
            $availableMethods[] = [
                'code' => $method->getCode(),
                'label' => $method->getLabel(),
            ];
        }
        // Здесь можно добавить логику сортировки по приоритету, если потребуется
        return $availableMethods;
    }
}
