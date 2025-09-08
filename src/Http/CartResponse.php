<?php
declare(strict_types=1);

namespace App\Http;

use App\Entity\Cart;
use App\Service\CartDeltaBuilder;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};

final class CartResponse
{
    public function __construct(
        private CartEtags $etags,
        private CartDeltaBuilder $deltaBuilder
    ) {}

    /**
     * Создает ответ с учетом режима ответа
     */
    public function withCart(
        JsonResponse $resp,
        Cart $cart,
        Request $request,
        string $responseMode = 'full',
        array $changes = []
    ): JsonResponse {
        $payload = $this->buildPayload($cart, $request, $responseMode, $changes);
        $shouldReturnBody = $this->deltaBuilder->shouldReturnBody($responseMode, $request->getMethod());

        if (!$shouldReturnBody) {
            // Возвращаем 204 No Content для легких ответов
            $resp->setStatusCode(Response::HTTP_NO_CONTENT);
            $resp->setData(null);
        } else {
            $resp->setData($payload);
        }

        // Устанавливаем заголовки для всех ответов
        $this->setResponseHeaders($resp, $cart);

        return $resp;
    }

    /**
     * Строит payload в зависимости от режима ответа
     */
    private function buildPayload(Cart $cart, Request $request, string $responseMode, array $changes): array
    {
        switch ($responseMode) {
            case 'summary':
                return $this->deltaBuilder->buildSummary($cart);

            case 'delta':
                return $this->deltaBuilder->buildDelta($cart, $changes);

            case 'full':
            default:
                return $this->deltaBuilder->buildFull($cart);
        }
    }

    /**
     * Устанавливает стандартные заголовки для ответов корзины
     */
    private function setResponseHeaders(JsonResponse $resp, Cart $cart): void
    {
        $resp->setEtag($this->etags->make($cart));
        $resp->headers->set('Cache-Control', 'no-store');
        $resp->headers->set('Cart-Version', (string)$cart->getVersion());
        $resp->headers->set('Items-Count', (string)$cart->getItems()->count());
        $resp->headers->set('Totals-Subtotal', (string)$cart->getSubtotal());
        $resp->headers->set('Totals-Discount', (string)$cart->getDiscountTotal());
        $resp->headers->set('Totals-Total', (string)$cart->getTotal());
    }

    /**
     * Создает ответ для батч-операций
     */
    public function withBatchResult(
        JsonResponse $resp,
        Cart $cart,
        array $results,
        array $changes = []
    ): JsonResponse {
        $payload = [
            'version' => $cart->getVersion(),
            'results' => $results,
            'changedItems' => array_map(function($change) {
                if ($change['type'] === 'changed') {
                    $item = $change['item'];
                    return [
                        'id' => $item->getId(),
                        'qty' => $item->getQty(),
                        'rowTotal' => $item->getRowTotal(),
                        'effectiveUnitPrice' => $item->getEffectiveUnitPrice(),
                    ];
                }
                return null;
            }, array_filter($changes, fn($c) => $c['type'] === 'changed')),
            'removedItemIds' => array_map(
                fn($c) => $c['itemId'],
                array_filter($changes, fn($c) => $c['type'] === 'removed')
            ),
            'totals' => [
                'itemsCount' => $cart->getItems()->count(),
                'subtotal' => $cart->getSubtotal(),
                'discountTotal' => $cart->getDiscountTotal(),
                'total' => $cart->getTotal(),
            ],
        ];

        $resp->setData($payload);
        $this->setResponseHeaders($resp, $cart);

        return $resp;
    }

    /**
     * Создает ответ для ошибок батч-операций
     */
    public function withBatchError(
        JsonResponse $resp,
        string $error,
        array $results = [],
        int $statusCode = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        $resp->setData([
            'error' => $error,
            'results' => $results,
            'changedItems' => [],
            'removedItemIds' => [],
            'totals' => null,
        ]);
        $resp->setStatusCode($statusCode);

        return $resp;
    }
}
