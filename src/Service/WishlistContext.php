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
        if ($user) {
            return $this->wishlists->findByUser($user) ?? $this->manager->createForUser($user);
        }

        $token = $request?->cookies->get($this->cookieName);
        if ($token) {
            return $this->wishlists->findByToken($token) ?? $this->manager->createForGuest($token);
        }

        $newToken = bin2hex(random_bytes(16));
        return $this->manager->createForGuest($newToken);
    }
}


