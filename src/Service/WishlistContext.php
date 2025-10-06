<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Wishlist;
use App\Repository\WishlistRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class WishlistContext
{
    public function __construct(
        private RequestStack $requestStack,
        private WishlistRepository $wishlists,
        private WishlistManager $manager,
        private string $cookieName,
    ) {}

    public function getOrCreate(?User $user = null): Wishlist
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request?->getSession();
        if ($user) {
            return $this->wishlists->findByUser($user) ?? $this->manager->createForUser($user);
        }

        // 1) Пробуем cookie
        $token = $request?->cookies->get($this->cookieName);
        // 2) Фолбэк: если cookie ещё нет, но есть токен в сессии — используем его
        if (!$token && $session && $session->has('wishlist_token')) {
            $token = (string)$session->get('wishlist_token');
        }
        if ($token) {
            return $this->wishlists->findByToken($token) ?? $this->manager->createForGuest($token);
        }

        // 3) Генерируем новый токен и кладём его в сессию, чтобы параллельные запросы не создали дубликаты
        $newToken = bin2hex(random_bytes(16));
        if ($session) {
            $session->set('wishlist_token', $newToken);
        }
        return $this->manager->createForGuest($newToken);
    }
}


