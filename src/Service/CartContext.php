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
    private const CART_ID_COOKIE = 'cart_id';
    private const CART_TTL_DAYS = 180;

    public function __construct(
        private EntityManagerInterface $em,
        private CartRepository $carts,
        private LockFactory $lockFactory,
        private RequestStack $requestStack,
        private CartCookieFactory $cookieFactory,
    ) {}

    public function getOrCreate(?int $userId, Response $response): Cart
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new \RuntimeException('No current request available');
        }

        // Получаем cart_id из cookie (поддержка старого и нового формата)
        $cartIdString = $request->cookies->get(self::CART_ID_COOKIE);
        $legacyCartIdString = $request->cookies->get('cart_id'); // legacy fallback
        $cartId = null; // Инициализируем переменную

        // Временное логирование для отладки
        error_log("CartContext: userId=" . ($userId ?? 'null') . ", cartIdString=" . ($cartIdString ?? 'null') . ", legacy=" . ($legacyCartIdString ?? 'null'));

        $cart = null;

        // Сначала пытаемся найти по токену (новый формат)
        if ($cartIdString) {
            $cart = $this->carts->findActiveByToken($cartIdString);
            if ($cart) {
                error_log("CartContext: found cart by token: " . $cart->getIdString());
                return $cart;
            }
        }

        // Fallback на старый формат ULID
        $cartIdString = $cartIdString ?: $legacyCartIdString;
        if ($cartIdString) {
            try {
                $cartId = Ulid::fromString($cartIdString);
                error_log("CartContext: parsed ULID=" . $cartId->toBase32());
            } catch (\InvalidArgumentException $e) {
                error_log("CartContext: invalid ULID format - " . $e->getMessage());
                $cartId = null;
            }
        }

        // Формируем ключ для блокировки на основе IP и User-Agent
        $lockKey = 'cart_creation_' . md5($request->getClientIp() . $request->headers->get('User-Agent'));

        $lock = $this->lockFactory->createLock($lockKey, 30); // 30 секунд таймаут

        $lock->acquire(true);

        try {
            $now = new \DateTimeImmutable();
            $ttl = $now->modify('+' . self::CART_TTL_DAYS . ' days');

            $cart = null;

            if ($cartId) {
                $cart = $this->carts->findActiveById($cartId);
                error_log("CartContext: found cart by ULID=" . ($cart ? 'yes (' . $cart->getItems()->count() . ' items)' : 'no'));
            }

            if (!$cart) {
                // Проверяем, есть ли активная корзина пользователя
                if ($userId) {
                    $cart = $this->carts->findActiveByUserId($userId);
                    error_log("CartContext: found cart by userId=" . ($cart ? 'yes (' . $cart->getItems()->count() . ' items)' : 'no'));
                }
            }

            if ($cart) {
                // Корзина найдена, продлеваем её только для write-операций
                error_log("CartContext: using existing cart with " . $cart->getItems()->count() . " items");
                // Мутации выполняются только в getOrCreateForWrite()
            } else {
                // Создаем новую корзину
                error_log("CartContext: creating new cart");
                $cart = Cart::createNew($userId, $ttl);
                $this->em->persist($cart);
                $this->em->flush();
            }

            // Устанавливаем cookie через фабрику (используем токен вместо ULID для безопасности)
            $cookieValue = $cart->getToken() ?? $cart->getIdString(); // fallback на ULID если токена нет
            $cookie = $this->cookieFactory->build($request, $cookieValue);
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
