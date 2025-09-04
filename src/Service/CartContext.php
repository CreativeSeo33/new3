<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
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
    ) {}

    public function getOrCreate(?int $userId, Response $response): Cart
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new \RuntimeException('No current request available');
        }

        // Получаем cart_id из cookie
        $cartIdString = $request->cookies->get(self::CART_ID_COOKIE);
        $cartId = null;

        // Временное логирование для отладки
        error_log("CartContext: userId=" . ($userId ?? 'null') . ", cartIdString=" . ($cartIdString ?? 'null'));

        if ($cartIdString) {
            try {
                $cartId = Ulid::fromString($cartIdString);
                error_log("CartContext: parsed ULID=" . $cartId->toBase32());
            } catch (\InvalidArgumentException $e) {
                error_log("CartContext: invalid ULID format - " . $e->getMessage());
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
                // Корзина найдена, продлеваем её
                error_log("CartContext: using existing cart with " . $cart->getItems()->count() . " items");
                if ($userId && !$cart->getUserId()) {
                    $cart->setUserId($userId);
                }
                $cart->prolong($ttl);
                $this->em->flush();
            } else {
                // Создаем новую корзину
                error_log("CartContext: creating new cart");
                $cart = Cart::createNew($userId, $ttl);
                $this->em->persist($cart);
                $this->em->flush();
            }

            // Устанавливаем cookie
            $cookie = Cookie::create(
                self::CART_ID_COOKIE,
                $cart->getIdString(),
                $ttl,
                '/',
                null,
                $request->isSecure(),
                true, // httpOnly
                false,
                Cookie::SAMESITE_LAX
            );

            $response->headers->setCookie($cookie);

            return $cart;
        } finally {
            $lock->release();
        }
    }
}
