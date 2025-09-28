<?php
declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class WishlistCookieFactory
{
    public function __construct(
        private readonly bool $forceSecureInProd = true,
        private readonly bool $useHostPrefix = true,
        private readonly string $cookieName = 'wishlist_token',
        private readonly int $ttlDays = 365,
        private readonly ?string $domain = null,
        private readonly string $sameSite = Cookie::SAMESITE_LAX,
    ) {}

    public function getCookieName(): string
    {
        return $this->cookieName();
    }

    public function build(Request $request, string $value): Cookie
    {
        $expires = (new \DateTimeImmutable())->modify(sprintf('+%d days', $this->ttlDays));
        $secure = $this->shouldBeSecure($request);
        $name = $this->cookieName();
        $path = '/';
        $domain = $this->useHostPrefix ? null : $this->domain;

        return Cookie::create(
            $name,
            $value,
            $expires,
            $path,
            $domain,
            $secure,
            true,
            false,
            $this->sameSite
        );
    }

    private function cookieName(): string
    {
        return $this->useHostPrefix ? '__Host-' . $this->cookieName : $this->cookieName;
    }

    private function shouldBeSecure(Request $request): bool
    {
        if ($this->forceSecureInProd && in_array($_ENV['APP_ENV'] ?? 'prod', ['prod', 'staging'], true)) {
            return true;
        }
        return $request->isSecure();
    }
}


