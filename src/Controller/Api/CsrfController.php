<?php
declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/api')]
final class CsrfController extends AbstractController
{
    #[Route('/csrf', name: 'api_csrf_token', methods: ['GET'])]
    public function token(Request $request, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        // гарантируем старт сессии
        $request->getSession()?->start();

        $token = $tokens->getToken('api')->getValue();

        $resp = new JsonResponse(['csrfToken' => $token]);
        $resp->headers->set('Cache-Control', 'no-store');
        return $resp;
    }
}
