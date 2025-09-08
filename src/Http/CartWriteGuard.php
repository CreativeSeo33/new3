<?php
declare(strict_types=1);

namespace App\Http;

use App\Entity\Cart;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;

final class CartWriteGuard
{
    public function __construct(
        private CartEtags $etags,
        private bool $requirePrecondition = false
    ) {}

    public function assertPrecondition(Request $r, Cart $cart): void
    {
        $etag = $this->etags->make($cart);
        $ifMatch = $r->headers->get('If-Match');

        if ($ifMatch !== null) {
            if (!$this->etags->equals($ifMatch, $etag)) {
                throw new PreconditionFailedHttpException('Cart ETag mismatch');
            }
            return;
        }

        $clientVersion = null;
        if ($r->getContentTypeFormat() === 'json') {
            $json = json_decode($r->getContent() ?: 'null', true);
            if (is_array($json) && array_key_exists('version', $json)) {
                $clientVersion = (int)$json['version'];
            }
        }
        if ($clientVersion === null && $r->query->has('version')) {
            $clientVersion = (int)$r->query->get('version');
        }

        if ($clientVersion !== null) {
            if ($clientVersion !== $cart->getVersion()) {
                throw new PreconditionFailedHttpException('Cart version mismatch');
            }
            return;
        }

        if ($this->requirePrecondition) {
            throw new PreconditionRequiredHttpException('If-Match or version is required');
        }
        // Иначе — совместимый режим: разрешаем запись без предиката.
    }
}
