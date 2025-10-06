<?php
declare(strict_types=1);

namespace App\Controller\Api\Customer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\HttpFoundation\Response;
use App\Service\Auth\RefreshTokenManager;
use App\Service\Auth\OneTimeTokenManager;
use App\Service\Auth\DisposableEmailChecker;
use App\Service\Auth\MailerService;
use App\Entity\UserOneTimeToken;
use App\Entity\UserRefreshToken;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use App\Service\Auth\AntiTimingService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api/customer/auth', name: 'customer_auth_')]
final class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        DisposableEmailChecker $deny,
        OneTimeTokenManager $ott,
        MailerService $mailer,
        #[Autowire(service: 'limiter.auth_register')] RateLimiterFactory $auth_register,
        AntiTimingService $antiTiming
    ): JsonResponse
    {
        $limit = $auth_register->create($request->getClientIp() ?? 'ip');
        if (false === $limit->consume(1)->isAccepted()) {
            return new JsonResponse(['message' => 'Too Many Requests'], 429);
        }

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        // Anti-enumeration: одинаковые ответы
        $generic = new JsonResponse(['status' => 'ok'], 201);
        if ($email === '' || $password === '' || $deny->isDisposable($email)) {
            $antiTiming->sleepOnFailure();
            return $generic;
        }

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $antiTiming->sleepOnFailure();
            return $generic;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($email); // временно используем email как name для совместимости
        // Сохраняем базовую роль в БД, чтобы не было пустого массива в storage
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($hasher->hashPassword($user, $password));
        $em->persist($user);
        $em->flush();

        // Создаём verify токен и отправляем письмо
        [$raw, $entity] = $ott->create($user, UserOneTimeToken::TYPE_VERIFY_EMAIL, (int) $this->getParameter('app.auth.verify_ttl'));
        $frontend = (string) $this->getParameter('app.frontend_base_url');
        $link = rtrim($frontend, '/') . '/auth/login?verify_token=' . rawurlencode($raw) . '&email=' . rawurlencode($email);
        $mailer->sendVerifyEmail($email, $link);

        return $generic;
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        AuthenticationSuccessHandler $successHandler,
        RefreshTokenManager $refreshManager,
        AntiTimingService $antiTiming
    ): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $email = (string) ($payload['email'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            $antiTiming->sleepOnFailure();
            return new JsonResponse(['message' => 'Invalid credentials'], 401);
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            $antiTiming->sleepOnFailure();
            return new JsonResponse(['message' => 'Invalid credentials'], 401);
        }

        // Обновляем lastLoginAt
        $user->setLastLoginAt(new \DateTimeImmutable());
        $em->flush();

        // Используем Lexik AuthenticationSuccessHandler, чтобы выставить cookies
        $lexikResponse = $successHandler->handleAuthenticationSuccess($user);

        // Создаём refresh, ставим cookie вручную
        [$rawRefresh, $storedRefresh] = $refreshManager->create($user, $request);
        $refreshCookie = $refreshManager->makeCookie($rawRefresh);

        $data = [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'isVerified' => $user->isVerified(),
            ],
        ];

        // Собираем cookies из ответа Lexik и возвращаем свой JsonResponse с теми же cookies
        $json = new JsonResponse($data, $lexikResponse->getStatusCode());
        foreach ($lexikResponse->headers->getCookies() as $cookie) {
            $json->headers->setCookie($cookie);
        }
        $json->headers->setCookie($refreshCookie);
        return $json;
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request, EntityManagerInterface $em, RefreshTokenManager $refreshManager): Response
    {
        // Для корректной ротации не требуем валидный access: используем refresh из cookie и ищем пользователя
        $raw = (string) $request->cookies->get('__Host-ref', '');
        if ($raw === '') {
            return new JsonResponse(['message' => 'Unauthorized'], 401);
        }

        // Находим по токену пользователя (без требования валидного access)
        $stored = $refreshManager->verifyGlobal($raw);
        if (!$stored) {
            return new JsonResponse(['message' => 'Unauthorized'], 401);
        }
        $user = $stored->getUser();

        // Ротация refresh + новый access
        [$newRaw, $newStored] = $refreshManager->rotate($stored, $user, $request);
        $newRefreshCookie = $refreshManager->makeCookie($newRaw);

        // Обновим access через Lexik
        /** @var AuthenticationSuccessHandler $successHandler */
        $successHandler = $this->container->get(AuthenticationSuccessHandler::class);
        $lexikResponse = $successHandler->handleAuthenticationSuccess($user);

        $json = new JsonResponse(['status' => 'ok'], $lexikResponse->getStatusCode());
        foreach ($lexikResponse->headers->getCookies() as $cookie) {
            $json->headers->setCookie($cookie);
        }
        $json->headers->setCookie($newRefreshCookie);
        return $json;
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request, RefreshTokenManager $refreshManager): JsonResponse
    {
        $cookie = $refreshManager->expireCookie();
        $res = new JsonResponse(null, 204);
        $res->headers->setCookie($cookie);
        return $res;
    }

    #[Route('/revoke-all', name: 'revoke_all', methods: ['POST'])]
    public function revokeAll(EntityManagerInterface $em, RefreshTokenManager $refreshManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $user */
        $user = $this->getUser();
        $refreshManager->revokeAll($user);
        // Сбросим версию токенов для немедленной инвалидции access
        $user->incrementTokenVersion();
        $em->flush();
        return new JsonResponse(null, 204);
    }
}


