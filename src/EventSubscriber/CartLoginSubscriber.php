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
    private const CART_ID_COOKIE = 'cart_id';
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

        // 1. Получить cart_id из cookie запроса
        $cartIdString = $request->cookies->get(self::CART_ID_COOKIE);
        if (!$cartIdString) {
            return; // 3. Если cart_id нет, ничего не делать
        }

        // 2. Найти гостевую корзину по cart_id
        try {
            $guestCartId = Ulid::fromString($cartIdString);
            $guestCart = $this->carts->findActiveById($guestCartId);
        } catch (\InvalidArgumentException) {
            return; // Неверный формат cart_id
        }

        if (!$guestCart) {
            return; // Гостевая корзина не найдена
        }

        // 4. Найти корзину пользователя
        $userCart = $this->carts->findActiveByUserId($userId);

        $finalCart = null;

        if ($userCart && $guestCart) {
            // 6. Сливаем гостевую в корзину юзера
            $finalCart = $this->manager->merge($userCart, $guestCart);
            // УДАЛЯЕМ гостевую корзину
            $this->em->remove($guestCart);
        } elseif ($guestCart) {
            // Просто привязываем гостевую корзину к пользователю
            $guestCart->setUserId($userId);
            $finalCart = $guestCart;
        }

        if ($finalCart) {
            // Продлеваем cookie для итоговой корзины через фабрику
            $cookieValue = $finalCart->getToken() ?? $finalCart->getIdString(); // fallback на ULID если токена нет
            $cookie = $this->cookieFactory->build($request, $cookieValue);

            $response = $event->getResponse();
            if ($response) {
                $response->headers->setCookie($cookie);
            }
        }

        $this->em->flush();
    }
}


