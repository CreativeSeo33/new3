<?php
declare(strict_types=1);

namespace App\Http;

use App\Entity\Cart;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CartResponse
{
    public function __construct(private CartEtags $etags) {}

    public function withCart(JsonResponse $resp, Cart $cart, array $payload): JsonResponse
    {
        // Вставляем актуальные version/etag в payload по желанию
        $payload['version'] = $cart->getVersion();
        $resp->setData($payload);

        $resp->setEtag($this->etags->make($cart));
        // Write-ответы не кэшируем
        $resp->headers->set('Cache-Control', 'no-store');
        return $resp;
    }
}
