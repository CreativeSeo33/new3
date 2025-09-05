<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LoggerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    public function __construct(
        private LoggerService $logger
    ) {}

    #[Route('/test-cart', name: 'test_cart')]
    public function testCart(Request $request): Response
    {
        $this->logger->logRequest($request->getMethod(), $request->getUri());

        $this->logger->info('Test cart page accessed', [
            'user_agent' => $request->headers->get('User-Agent'),
            'ip' => $request->getClientIp()
        ]);

        return new Response(file_get_contents(__DIR__.'/../../test-cart.html'));
    }

    #[Route('/api/test', name: 'api_test', methods: ['GET'])]
    public function apiTest(Request $request): JsonResponse
    {
        $this->logger->logRequest($request->getMethod(), $request->getUri());

        $this->logger->info('API test endpoint called', [
            'params' => $request->query->all(),
            'headers' => $request->headers->all()
        ]);

        $response = $this->json([
            'status' => 'ok',
            'message' => 'API работает',
            'timestamp' => time(),
            'logged' => true
        ]);

        $this->logger->info('API test response sent', [
            'status_code' => $response->getStatusCode()
        ]);

        return $response;
    }

}
