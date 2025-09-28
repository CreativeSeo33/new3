<?php
declare(strict_types=1);

namespace App\Twig;

use App\Service\WishlistContext;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class WishlistExtension extends AbstractExtension
{
    public function __construct(
        private WishlistContext $wishlistContext,
        private Security $security
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wishlist_count', [$this, 'wishlistCount']),
            new TwigFunction('wishlist_has', [$this, 'wishlistHas'])
        ];
    }

    public function wishlistCount(): int
    {
        $user = $this->security->getUser();
        $w = $this->wishlistContext->getOrCreate($user);
        return $w->getItems()->count();
    }

    public function wishlistHas(int $productId): bool
    {
        $user = $this->security->getUser();
        $w = $this->wishlistContext->getOrCreate($user);
        foreach ($w->getItems() as $it) {
            if ($it->getProduct()->getId() === $productId) return true;
        }
        return false;
    }
}


