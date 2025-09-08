<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Http\CartCookieFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class CartCookieFactoryTest extends TestCase
{
    private CartCookieFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new CartCookieFactory(
            forceSecureInProd: false, // для тестов
            useHostPrefix: true,
            cookieName: 'cart_id',
            ttlDays: 180,
            domain: null,
            sameSite: Cookie::SAMESITE_LAX
        );
    }

    public function testBuildCreatesSecureCookieInProd(): void
    {
        // Имитируем продакшн окружение
        $_ENV['APP_ENV'] = 'prod';

        // Создаем фабрику с forceSecureInProd = true
        $prodFactory = new CartCookieFactory(
            forceSecureInProd: true,
            useHostPrefix: true,
            cookieName: 'cart_id',
            ttlDays: 180,
            domain: null,
            sameSite: Cookie::SAMESITE_LAX
        );

        $request = new Request();
        $cookie = $prodFactory->build($request, 'test-token');

        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEquals(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        $this->assertEquals('__Host-cart_id', $cookie->getName());
        $this->assertEquals('test-token', $cookie->getValue());
        $this->assertNull($cookie->getDomain()); // __Host- не позволяет указывать domain

        // Очищаем переменную окружения
        unset($_ENV['APP_ENV']);
    }

    public function testBuildCreatesCookieWithCorrectAttributes(): void
    {
        $request = new Request();
        $cookie = $this->factory->build($request, 'test-token');

        $this->assertEquals('__Host-cart_id', $cookie->getName());
        $this->assertEquals('test-token', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEquals(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        $this->assertEquals('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
    }

    public function testDeleteCreatesExpiredCookie(): void
    {
        $request = new Request();
        $cookie = $this->factory->delete($request);

        $this->assertEquals('__Host-cart_id', $cookie->getName());
        $this->assertEquals('', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        // В dev окружении без forceSecureInProd Secure может быть false
        $this->assertEquals(Cookie::SAMESITE_LAX, $cookie->getSameSite());

        // Проверяем, что cookie истек (дата в прошлом)
        $this->assertLessThan(time(), $cookie->getExpiresTime() ?? 0);
    }

    public function testCookieNameWithoutHostPrefix(): void
    {
        $factory = new CartCookieFactory(
            forceSecureInProd: false,
            useHostPrefix: false,
            cookieName: 'cart_id',
            ttlDays: 180,
            domain: '.example.com',
            sameSite: Cookie::SAMESITE_LAX
        );

        $request = new Request();
        $cookie = $factory->build($request, 'test-token');

        $this->assertEquals('cart_id', $cookie->getName());
        $this->assertEquals('.example.com', $cookie->getDomain());
    }
}
