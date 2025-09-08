<?php
declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class CartCookieFactory
{
    public function __construct(
        private readonly bool $forceSecureInProd = true,
        private readonly bool $useHostPrefix = true,
        private readonly string $cookieName = 'cart_id',
        private readonly int $ttlDays = 180,
        private readonly ?string $domain = null,
        private readonly string $sameSite = Cookie::SAMESITE_LAX,
    ) {}

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
            true,              // httpOnly
            false,             // raw
            $this->sameSite
        );
    }

    public function delete(Request $request): Cookie
    {
        $secure = $this->shouldBeSecure($request);
        $name = $this->cookieName();
        $path = '/';
        $domain = $this->useHostPrefix ? null : $this->domain;

        return Cookie::create(
            $name,
            '',                // пустое значение
            (new \DateTimeImmutable('@0')), // истёк в прошлом
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
        // Всегда Secure в prod + при HTTPS/за доверенным прокси
        if ($this->forceSecureInProd && in_array($_ENV['APP_ENV'] ?? 'prod', ['prod', 'staging'], true)) {
            return true;
        }
        return $request->isSecure();
    }
}
