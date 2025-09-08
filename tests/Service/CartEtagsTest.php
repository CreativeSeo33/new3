<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Cart;
use App\Http\CartEtags;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CartEtagsTest extends KernelTestCase
{
    private CartEtags $etags;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->etags = $container->get(CartEtags::class);
    }

    public function testGeneratesConsistentEtag(): void
    {
        // Создаем тестовую корзину
        $cart = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $cart->setUpdatedAt(new \DateTimeImmutable('2023-12-01 12:00:00'));

        // Используем рефлексию для установки версии (поскольку Doctrine управляет ею)
        $reflection = new \ReflectionClass($cart);
        $versionProp = $reflection->getProperty('version');
        $versionProp->setAccessible(true);
        $versionProp->setValue($cart, 5);

        // Генерируем ETag
        $etag1 = $this->etags->make($cart);
        $etag2 = $this->etags->make($cart);

        // Проверяем, что ETag консистентный
        $this->assertEquals($etag1, $etag2);
        $this->assertStringStartsWith('W/"cart:', $etag1);
        $this->assertStringContainsString('5', $etag1); // версия
        $this->assertStringContainsString('1701432000', $etag1); // timestamp
    }

    public function testEtagChangesWithVersion(): void
    {
        $cart = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $cart->setUpdatedAt(new \DateTimeImmutable('2023-12-01 12:00:00'));

        // Используем рефлексию для установки версии
        $reflection = new \ReflectionClass($cart);
        $versionProp = $reflection->getProperty('version');
        $versionProp->setAccessible(true);
        $versionProp->setValue($cart, 1);

        $etag1 = $this->etags->make($cart);

        $versionProp->setValue($cart, 2);
        $etag2 = $this->etags->make($cart);

        $this->assertNotEquals($etag1, $etag2);
    }

    public function testEqualsMethod(): void
    {
        $cart = Cart::createNew(null, new \DateTimeImmutable('+180 days'));
        $cart->setUpdatedAt(new \DateTimeImmutable('2023-12-01 12:00:00'));

        // Используем рефлексию для установки версии
        $reflection = new \ReflectionClass($cart);
        $versionProp = $reflection->getProperty('version');
        $versionProp->setAccessible(true);
        $versionProp->setValue($cart, 1);

        $etag = $this->etags->make($cart);

        // Тестируем сравнение с самим собой
        $this->assertTrue($this->etags->equals($etag, $etag));

        // Тестируем сравнение с другим ETag
        $versionProp->setValue($cart, 2);
        $otherEtag = $this->etags->make($cart);
        $this->assertFalse($this->etags->equals($etag, $otherEtag));

        // Тестируем сравнение сильного и слабого ETag
        $strongEtag = str_replace('W/', '', $etag);
        $this->assertTrue($this->etags->equals($strongEtag, $etag));
        $this->assertTrue($this->etags->equals($etag, $strongEtag));
    }
}
