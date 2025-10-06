<?php
declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Request;

final class AuthFlowTest extends WebTestCase
{
    public function testRegisterLoginRefreshMeRevoke(): void
    {
        /** @var AbstractBrowser $client */
        $client = static::createClient();

        // Register
        $client->request(Request::METHOD_POST, '/api/customer/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'user@example.com',
            'password' => 'P@ssw0rd!!!',
        ]));
        self::assertContains($client->getResponse()->getStatusCode(), [201, 202, 200]);

        // Login
        $client->request(Request::METHOD_POST, '/api/customer/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'user@example.com',
            'password' => 'P@ssw0rd!!!',
        ]));
        self::assertTrue($client->getResponse()->isOk());
        $cookies = $client->getResponse()->headers->getCookies();
        $this->assertNotEmpty($cookies);

        // Forward cookies to next requests
        foreach ($cookies as $cookie) {
            $client->getCookieJar()->set($cookie);
        }

        // Me (should be authenticated)
        $client->request(Request::METHOD_GET, '/api/customer/me');
        self::assertTrue($client->getResponse()->isOk());

        // Refresh
        $client->request(Request::METHOD_POST, '/api/customer/auth/refresh');
        self::assertTrue($client->getResponse()->isOk());

        // Revoke all
        $client->request(Request::METHOD_POST, '/api/customer/auth/revoke-all');
        self::assertEquals(204, $client->getResponse()->getStatusCode());
    }
}


