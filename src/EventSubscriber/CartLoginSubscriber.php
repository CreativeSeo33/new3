<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\CartCookieFactory;
use App\Repository\CartRepository;
use App\Service\CartManager;
use App\Entity\User as AppUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Uid\Ulid;

final class CartLoginSubscriber implements EventSubscriberInterface
{
    private const CART_TTL_DAYS = 180;

    public function __construct(
        private CartRepository $carts,
        private CartManager $manager,
        private EntityManagerInterface $em,
        private CartCookieFactory $cookieFactory,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof AppUser) {
            return;
        }

        $userId = $user->getId();
        $request = $event->getRequest();
        // 1. Читаем cookie: новый формат как токен, fallback на legacy как ULID
        $tokenCookie = $request->cookies->get($this->cookieFactory->getCookieName());
        $legacyCookie = $request->cookies->get('cart_id');

        $guestCart = null;

        // 2. Сначала пытаемся найти гостевую корзину по токену (новый формат)
        if ($tokenCookie) {
            $guestCart = $this->carts->findActiveByToken($tokenCookie);
            if ($guestCart) {
                error_log("CartLoginSubscriber: found guest cart by token: " . $guestCart->getIdString());
            }
        }

        // 3. Fallback: legacy cookie как ULID (временная поддержка миграции)
        if (!$guestCart && $legacyCookie) {
            try {
                $guestCartId = Ulid::fromString($legacyCookie);
                $guestCart = $this->carts->findActiveById($guestCartId);
                if ($guestCart) {
                    error_log("CartLoginSubscriber: legacy ULID fallback used for cart: " . $guestCart->getIdString());
                }
            } catch (\InvalidArgumentException) {
                // Игнорируем неверный формат ULID
            }
        }

        if (!$guestCart) {
            return; // Гостевая корзина не найдена
        }

        // 4. Найти корзину пользователя
        $userCart = $this->carts->findActiveByUserId($userId);

        $finalCart = null;

        if ($userCart && $guestCart) {
            if ($userCart->getId() && $guestCart->getId() && $userCart->getId()->equals($guestCart->getId())) {
                // Нечего сливать — это одна и та же корзина
                $finalCart = $userCart;
            } else {
            // 5. Сливаем гостевую в корзину юзера
                $finalCart = $this->manager->merge($userCart, $guestCart);
                // УДАЛЯЕМ гостевую корзину
                $this->em->remove($guestCart);
            }
        } elseif ($guestCart) {
            // Просто привязываем гостевую корзину к пользователю
            $guestCart->setUserId($userId);
            $guestCart->setToken(null); // Убираем токен при присвоении пользователю
            $finalCart = $guestCart;
        }

        if ($finalCart) {
            // Устанавливаем cookie только с токеном для итоговой корзины
            $cookie = $this->cookieFactory->build($request, $finalCart->ensureToken());

            $response = $event->getResponse();
            if ($response) {
                $response->headers->setCookie($cookie);
            }
        }

        $this->em->flush();
    }
}


