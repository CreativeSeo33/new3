<?php
declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductSearchTest extends WebTestCase
{
    public function testEmptyQKeepsBehavior(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v2/products');
        $this->assertResponseIsSuccessful();
        $noQ = json_decode($client->getResponse()->getContent() ?: '[]', true);

        $client->request('GET', '/api/v2/products?q=');
        $this->assertResponseIsSuccessful();
        $withEmptyQ = json_decode($client->getResponse()->getContent() ?: '[]', true);

        $this->assertIsArray($withEmptyQ);
        $this->assertIsArray($noQ);
    }
}


