<?php
declare(strict_types=1);

namespace App\Http;

use App\Entity\Cart;

final class CartEtags
{
    public function make(Cart $cart): string
    {
        $id = $cart->getIdString();
        $v = $cart->getVersion();
        $ts = $cart->getUpdatedAt()?->getTimestamp() ?? 0;
        return sprintf('W/"cart:%s.%d.%d"', $id, $v, $ts);
    }

    public function equals(string $ifMatch, string $etag): bool
    {
        $n = static fn(string $s) => ltrim(trim($s), 'W/');
        return $n($ifMatch) === $n($etag);
    }
}
