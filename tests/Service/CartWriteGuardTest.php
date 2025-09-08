<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Cart;
use App\Http\CartWriteGuard;
use App\Http\CartEtags;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;

final class CartWriteGuardTest extends KernelTestCase
{
    private CartWriteGuard $guard;
    private CartEtags $etags;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->guard = $container->get(CartWriteGuard::class);
        $this->etags = $container->get(CartEtags::class);
    }

    public function testAllowsWriteWithoutPreconditionInCompatibleMode(): void
    {
        $cart = $this->createTestCart();
        $request = Request::create('/', 'POST');

        // Не должно выбросить исключение
        $this->guard->assertPrecondition($request, $cart);
        $this->assertTrue(true);
    }

    public function testValidatesIfMatchHeader(): void
    {
        $cart = $this->createTestCart();
        $etag = $this->etags->make($cart);

        $request = Request::create('/', 'POST');
        $request->headers->set('If-Match', $etag);

        // Не должно выбросить исключение
        $this->guard->assertPrecondition($request, $cart);
        $this->assertTrue(true);
    }

    public function testRejectsInvalidIfMatchHeader(): void
    {
        $cart = $this->createTestCart();

        $request = Request::create('/', 'POST');
        $request->headers->set('If-Match', 'W/"cart:invalid"');

        $this->expectException(PreconditionFailedHttpException::class);
        $this->guard->assertPrecondition($request, $cart);
    }

    public function testValidatesVersionInJsonBody(): void
    {
        $cart = $this->createTestCart();

        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['version' => 1]));
        $request->headers->set('Content-Type', 'application/json');

        // Не должно выбросить исключение
        $this->guard->assertPrecondition($request, $cart);
        $this->assertTrue(true);
    }

    public function testValidatesVersionInQueryString(): void
    {
        $cart = $this->createTestCart();

        $request = Request::create('/', 'POST', ['version' => 1]);

        // Не должно выбросить исключение
        $this->guard->assertPrecondition($request, $cart);
        $this->assertTrue(true);
    }

    public function testRejectsInvalidVersion(): void
    {
        $cart = $this->createTestCart();

        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['version' => 999]));
        $request->headers->set('Content-Type', 'application/json');

        $this->expectException(PreconditionFailedHttpException::class);
        $this->guard->assertPrecondition($request, $cart);
    }

    private function createTestCart(): Cart
    {
        $cart = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $cart->setVersion(1);
        $cart->setUpdatedAt(new \DateTimeImmutable('2023-12-01 12:00:00'));
        return $cart;
    }
}
