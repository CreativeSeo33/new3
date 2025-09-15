<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Http\CartCookieFactory;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Uid\Ulid;

final class CartContext
{
    /**
     * AI-META v1
     * role: Поиск/создание текущей корзины с токеном в cookie; миграция legacy cookie
     * module: Cart
     * dependsOn:
     *   - Doctrine\ORM\EntityManagerInterface
     *   - App\Repository\CartRepository
     *   - Symfony\Component\Lock\LockFactory
     *   - Symfony\Component\HttpFoundation\RequestStack
     *   - App\Http\CartCookieFactory
     * invariants:
     *   - Только токен‑cookie используется как первичный идентификатор гостевой корзины
     *   - TTL корзины продлевается только на write‑операциях; TTL по умолчанию 180 дней
     * transaction: em
     * lastUpdated: 2025-09-15
     */
    private const CART_TTL_DAYS = 180;

    public function __construct(
        private EntityManagerInterface $em,
        private CartRepository $carts,
        private LockFactory $lockFactory,
        private RequestStack $requestStack,
        private CartCookieFactory $cookieFactory,
    ) {}

    private function getTokenCookieName(): string
    {
        return $this->cookieFactory->getCookieName();
    }

    public function getOrCreate(?int $userId, Response $response): Cart
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new \RuntimeException('No current request available');
        }

        // Читаем cookie: новый формат (__Host-cart_id) как токен
        $tokenCookie = $request->cookies->get($this->getTokenCookieName());
        // Fallback на legacy cookie (cart_id) как ULID для миграции
        $legacyCookie = $request->cookies->get('cart_id');

        $cart = null;

        // 1) Сначала пытаемся найти корзину по токену (новый формат)
        if ($tokenCookie) {
            $cart = $this->carts->findActiveByToken($tokenCookie);
            if ($cart) {
                // Устанавливаем cookie с токеном (обновляем/продлеваем)
                $cookie = $this->cookieFactory->build($request, $cart->ensureToken());
                $response->headers->setCookie($cookie);
                return $cart;
            }
        }

        // 2) Fallback: legacy cookie как ULID (временная поддержка миграции)
        if (!$cart && $legacyCookie) {
            try {
                $cartId = Ulid::fromString($legacyCookie);
                $cart = $this->carts->findActiveById($cartId);
                if ($cart) {
                    // Устанавливаем новый cookie с токеном для будущих запросов
                    $cookie = $this->cookieFactory->build($request, $cart->ensureToken());
                    $response->headers->setCookie($cookie);
                    return $cart;
                }
            } catch (\InvalidArgumentException) {
                // Игнорируем неверный формат ULID
            }
        }

        // Формируем ключ для блокировки на основе IP и User-Agent
        $lockKey = 'cart_creation_' . md5($request->getClientIp() . $request->headers->get('User-Agent'));
        $lock = $this->lockFactory->createLock($lockKey, 30);

        $lock->acquire(true);

        try {
            $now = new \DateTimeImmutable();
            $ttl = $now->modify('+' . self::CART_TTL_DAYS . ' days');

            // Проверяем, есть ли активная корзина пользователя (если пользователь авторизован)
            if (!$cart && $userId) {
                $cart = $this->carts->findActiveByUserId($userId);
            }

            if (!$cart) {
                // Создаем новую корзину
                $cart = Cart::createNew($userId, $ttl);
                $this->em->persist($cart);
                $this->em->flush();
            }

            // Устанавливаем cookie только с токеном (без fallback на ULID)
            $cookie = $this->cookieFactory->build($request, $cart->ensureToken());
            $response->headers->setCookie($cookie);

            return $cart;
        } finally {
            $lock->release();
        }
    }

    /**
     * Получает корзину для операций записи с продлением TTL
     */
    public function getOrCreateForWrite(?int $userId, Response $response): Cart
    {
        $cart = $this->getOrCreate($userId, $response);

        // Выполняем мутации только для write-операций
        $now = new \DateTimeImmutable();
        $ttl = $now->modify('+' . self::CART_TTL_DAYS . ' days');

        if ($userId && !$cart->getUserId()) {
            $cart->setUserId($userId);
        }
        $cart->prolong($ttl);
        $this->em->flush();

        return $cart;
    }
}
