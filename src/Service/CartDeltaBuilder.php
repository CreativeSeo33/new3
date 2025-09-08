<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Сервис для построения оптимизированных ответов Cart API
 *
 * Поддерживает три режима ответа:
 * - full: полная корзина (текущая реализация)
 * - summary: минимальные данные для обновления UI totals
 * - delta: точечные изменения по позициям
 */
final class CartDeltaBuilder
{
    public function __construct(
        private CartCalculator $calculator,
        private LivePriceCalculator $livePrice
    ) {}

    /**
     * Определяет желаемый режим ответа из запроса
     */
    public function determineResponseMode(Request $request): string
    {
        // Приоритет у Prefer заголовка
        $prefer = $request->headers->get('Prefer', '');
        if ($prefer) {
            $this->parsePreferHeader($prefer);
        }

        // Fallback на query параметр
        $view = $request->query->get('view');
        if ($view && in_array($view, ['full', 'summary', 'delta'], true)) {
            return $view;
        }

        // По умолчанию для write-операций возвращаем delta
        return 'delta';
    }

    /**
     * Парсит Prefer заголовок для определения режима ответа
     */
    private function parsePreferHeader(string $prefer): ?string
    {
        // Пример: "return=minimal; profile="cart.delta""
        $parts = explode(';', $prefer);
        foreach ($parts as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'profile=')) {
                $profile = trim($part, '"');
                if ($profile === 'cart.delta') return 'delta';
                if ($profile === 'cart.summary') return 'summary';
                if ($profile === 'cart.full') return 'full';
            }
            if ($part === 'return=minimal') return 'delta';
            if ($part === 'return=representation') return 'full';
        }
        return null;
    }

    /**
     * Создает summary ответ (минимальные данные для UI)
     */
    public function buildSummary(Cart $cart): array
    {
        return [
            'version' => $cart->getVersion(),
            'itemsCount' => $cart->getItems()->count(),
            'subtotal' => $cart->getSubtotal(),
            'discountTotal' => $cart->getDiscountTotal(),
            'total' => $cart->getTotal(),
        ];
    }

    /**
     * Создает delta ответ (точечные изменения)
     */
    public function buildDelta(Cart $cart, array $changes): array
    {
        $changedItems = [];
        $removedItemIds = [];

        foreach ($changes as $change) {
            if ($change['type'] === 'changed') {
                $item = $change['item'];
                $changedItems[] = [
                    'id' => $item->getId(),
                    'qty' => $item->getQty(),
                    'rowTotal' => $item->getRowTotal(),
                    'effectiveUnitPrice' => $item->getEffectiveUnitPrice(),
                ];
            } elseif ($change['type'] === 'removed') {
                $removedItemIds[] = $change['itemId'];
            }
        }

        return [
            'version' => $cart->getVersion(),
            'changedItems' => $changedItems,
            'removedItemIds' => $removedItemIds,
            'totals' => [
                'itemsCount' => $cart->getItems()->count(),
                'subtotal' => $cart->getSubtotal(),
                'discountTotal' => $cart->getDiscountTotal(),
                'total' => $cart->getTotal(),
            ],
        ];
    }

    /**
     * Создает полный ответ (текущая реализация)
     */
    public function buildFull(Cart $cart, array $policyConfig = []): array
    {
        $policy = $cart->getPricingPolicy();

        $data = [
            'id' => $cart->getIdString(),
            'currency' => $cart->getCurrency(),
            'pricingPolicy' => $policy,
            'version' => $cart->getVersion(),
            'subtotal' => $cart->getSubtotal(),
            'discountTotal' => $cart->getDiscountTotal(),
            'total' => $cart->getTotal(),
            'shipping' => [
                'method' => $cart->getShippingMethod(),
                'cost' => $cart->getShippingCost(),
                'city' => $cart->getShipToCity(),
                'data' => $cart->getShippingData(),
            ],
            'items' => array_map(function($i) use ($policy) {
                $data = [
                    'id' => $i->getId(),
                    'productId' => $i->getProduct()->getId(),
                    'name' => $i->getProductName(),
                    'unitPrice' => $i->getUnitPrice(),
                    'optionsPriceModifier' => $i->getOptionsPriceModifier(),
                    'effectiveUnitPrice' => $i->getEffectiveUnitPrice(),
                    'qty' => $i->getQty(),
                    'rowTotal' => $i->getRowTotal(),
                    'pricedAt' => $i->getPricedAt()->format(DATE_ATOM),
                    'selectedOptions' => $i->getOptionsSnapshot() ?? $i->getSelectedOptionsData() ?? [],
                    'optionsHash' => $i->getOptionsHash(),
                ];

                // Добавляем live-данные только в LIVE режиме
                if ($policy === 'LIVE') {
                    $liveEffectiveUnitPrice = $this->livePrice->effectiveUnitPriceLive($i);
                    $liveRowTotal = $liveEffectiveUnitPrice * $i->getQty();

                    $data['currentEffectiveUnitPrice'] = $liveEffectiveUnitPrice;
                    $data['currentRowTotal'] = $liveRowTotal;
                    $data['priceChanged'] = $liveEffectiveUnitPrice !== $i->getEffectiveUnitPrice();
                }

                return $data;
            }, $cart->getItems()->toArray()),
        ];

        return $data;
    }

    /**
     * Анализирует изменения для генерации delta ответа
     * Используется для отслеживания изменений в рамках одной операции
     */
    public function analyzeChanges(Cart $cart, array $beforeItems, array $afterItems): array
    {
        $changes = [];

        // Анализ удаленных позиций
        foreach ($beforeItems as $itemId => $beforeItem) {
            if (!isset($afterItems[$itemId])) {
                $changes[] = [
                    'type' => 'removed',
                    'itemId' => $itemId,
                ];
            }
        }

        // Анализ измененных позиций
        foreach ($afterItems as $itemId => $afterItem) {
            if (!isset($beforeItems[$itemId])) {
                // Новая позиция
                $changes[] = [
                    'type' => 'changed',
                    'item' => $afterItem,
                ];
            } elseif ($beforeItems[$itemId]['qty'] !== $afterItem->getQty()) {
                // Измененное количество
                $changes[] = [
                    'type' => 'changed',
                    'item' => $afterItem,
                ];
            }
        }

        return $changes;
    }

    /**
     * Создает снимок позиций корзины для последующего сравнения
     */
    public function createItemsSnapshot(Cart $cart): array
    {
        $snapshot = [];
        foreach ($cart->getItems() as $item) {
            $snapshot[$item->getId()] = [
                'id' => $item->getId(),
                'qty' => $item->getQty(),
                'item' => $item,
            ];
        }
        return $snapshot;
    }

    /**
     * Определяет, нужно ли возвращать тело ответа
     */
    public function shouldReturnBody(string $responseMode, string $httpMethod): bool
    {
        // DELETE всегда возвращает 204 для успешных операций
        if ($httpMethod === 'DELETE') {
            return false;
        }

        // PATCH может возвращать 204 в delta режиме
        if ($httpMethod === 'PATCH' && $responseMode === 'delta') {
            return false;
        }

        // POST всегда возвращает тело
        return true;
    }
}
