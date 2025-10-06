<?php
declare(strict_types=1);

namespace App\Controller\Api\Customer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/customer/auth', name: 'customer_auth_')]
final class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        // Минимальный обработчик: принимает email/password и возвращает общий ответ
        // Реальная регистрация/почта/валидация добавляются отдельно
        return new JsonResponse(['status' => 'ok'], 201);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Минимальный обработчик: успешно отвечает, чтобы фронт не падал на 404
        // Реальная аутентификация с JWT/куками добавляется отдельно
        return new JsonResponse(['status' => 'ok', 'user' => null]);
    }
}


