<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route('/test-cart', name: 'test_cart')]
    public function testCart(): Response
    {
        return new Response(file_get_contents(__DIR__.'/../../test-cart.html'));
    }

    #[Route('/api/test', name: 'api_test', methods: ['GET'])]
    public function apiTest(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'message' => 'API работает',
            'timestamp' => time()
        ]);
    }

}
