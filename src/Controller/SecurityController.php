<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $lastUsername = $authenticationUtils->getLastUsername();
        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        // handled by Symfony security
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function apiLogin(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        $name = (string) ($payload['_username'] ?? $payload['username'] ?? $payload['name'] ?? '');
        $password = (string) ($payload['_password'] ?? $payload['password'] ?? '');
        if ($name === '' || $password === '') {
            return new JsonResponse(['message' => 'Invalid credentials.'], 401);
        }
        $user = $em->getRepository(User::class)->findOneBy(['name' => $name]);
        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['message' => 'Invalid credentials.'], 401);
        }
        // выдать JWT через Lexik JWT Manager (HS256)
        /** @var \Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface $jwtManager */
        $jwtManager = $this->container->get('lexik_jwt_authentication.jwt_manager');
        $token = $jwtManager->create($user);
        return new JsonResponse(['token' => $token]);
    }
}


