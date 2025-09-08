<?php
declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Cart;
use App\Entity\Product;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Тесты для оптимизированных режимов ответа Cart API
 */
class CartApiOptimizationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private CartRepository $carts;
    private ProductRepository $products;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->carts = static::getContainer()->get(CartRepository::class);
        $this->products = static::getContainer()->get(ProductRepository::class);
    }

    public function testDeltaResponseMode(): void
    {
        // Создаем тестовый продукт
        $product = new Product();
        $product->setName('Test Product');
        $product->setPrice(1000);
        $this->em->persist($product);
        $this->em->flush();

        // Добавляем товар с delta режимом
        $this->client->request('POST', '/api/cart/items', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Prefer' => 'return=minimal; profile="cart.delta"'
        ], json_encode([
            'productId' => $product->getId(),
            'qty' => 2
        ]));

        $this->assertEquals(201, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('version', $response);
        $this->assertArrayHasKey('changedItems', $response);
        $this->assertArrayHasKey('totals', $response);
        $this->assertCount(1, $response['changedItems']);
        $this->assertEquals(2, $response['changedItems'][0]['qty']);
        $this->assertEquals(2000, $response['changedItems'][0]['rowTotal']);
    }

    public function testSummaryResponseMode(): void
    {
        // Создаем корзину с товарами
        $cart = new Cart();
        $this->em->persist($cart);
        $this->em->flush();

        // Запрашиваем summary
        $this->client->request('GET', '/api/cart', [], [], [
            'HTTP_Prefer' => 'return=representation; profile="cart.summary"'
        ]);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('version', $response);
        $this->assertArrayHasKey('itemsCount', $response);
        $this->assertArrayHasKey('subtotal', $response);
        $this->assertArrayHasKey('total', $response);

        // Проверяем отсутствие детальных данных товаров
        $this->assertArrayNotHasKey('items', $response);
        $this->assertArrayNotHasKey('currency', $response);
    }

    public function testMinimalDeleteResponse(): void
    {
        // Создаем корзину с товарами
        $cart = new Cart();
        $this->em->persist($cart);
        $this->em->flush();

        // Удаляем корзину с minimal режимом
        $this->client->request('DELETE', '/api/cart', [], [], [
            'HTTP_Prefer' => 'return=minimal; profile="cart.delta"'
        ]);

        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());
        $this->assertEmpty($this->client->getResponse()->getContent());

        // Проверяем заголовки
        $headers = $this->client->getResponse()->getHeaders();
        $this->assertArrayHasKey('cart-version', $headers);
        $this->assertArrayHasKey('items-count', $headers);
        $this->assertArrayHasKey('totals-subtotal', $headers);
    }

    public function testBatchOperations(): void
    {
        // Создаем тестовые продукты
        $product1 = new Product();
        $product1->setName('Product 1');
        $product1->setPrice(1000);

        $product2 = new Product();
        $product2->setName('Product 2');
        $product2->setPrice(500);

        $this->em->persist($product1);
        $this->em->persist($product2);
        $this->em->flush();

        // Выполняем батч
        $this->client->request('POST', '/api/cart/batch', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Idempotency-Key' => 'test-batch-' . uniqid()
        ], json_encode([
            'operations' => [
                ['op' => 'add', 'productId' => $product1->getId(), 'qty' => 1],
                ['op' => 'add', 'productId' => $product2->getId(), 'qty' => 2],
            ],
            'atomic' => true
        ]));

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('version', $response);
        $this->assertArrayHasKey('results', $response);
        $this->assertArrayHasKey('changedItems', $response);
        $this->assertArrayHasKey('totals', $response);
        $this->assertCount(2, $response['results']);
        $this->assertCount(2, $response['changedItems']);
        $this->assertEquals(3000, $response['totals']['total']); // 1000 + 2*500
    }

    public function testIdempotencyKey(): void
    {
        // Создаем тестовый продукт
        $product = new Product();
        $product->setName('Test Product');
        $product->setPrice(1000);
        $this->em->persist($product);
        $this->em->flush();

        $idempotencyKey = 'test-idempotency-' . uniqid();

        // Первый запрос
        $this->client->request('POST', '/api/cart/items', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Idempotency-Key' => $idempotencyKey,
            'HTTP_Prefer' => 'return=representation; profile="cart.full"'
        ], json_encode([
            'productId' => $product->getId(),
            'qty' => 1
        ]));

        $this->assertEquals(201, $this->client->getResponse()->getStatusCode());
        $firstResponse = json_decode($this->client->getResponse()->getContent(), true);

        // Повторный запрос с тем же ключом
        $this->client->request('POST', '/api/cart/items', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Idempotency-Key' => $idempotencyKey,
            'HTTP_Prefer' => 'return=representation; profile="cart.full"'
        ], json_encode([
            'productId' => $product->getId(),
            'qty' => 1
        ]));

        $this->assertEquals(201, $this->client->getResponse()->getStatusCode());
        $secondResponse = json_decode($this->client->getResponse()->getContent(), true);

        // Ответы должны быть идентичны
        $this->assertEquals($firstResponse, $secondResponse);
    }

    public function testPreconditionHeaders(): void
    {
        // Создаем корзину
        $cart = new Cart();
        $cart->setVersion(5);
        $this->em->persist($cart);
        $this->em->flush();

        // Пытаемся добавить товар с неправильной версией
        $this->client->request('POST', '/api/cart/items', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_If-Match' => '"wrong-etag"'
        ], json_encode([
            'productId' => 1,
            'qty' => 1
        ]));

        $this->assertEquals(412, $this->client->getResponse()->getStatusCode());
    }
}
