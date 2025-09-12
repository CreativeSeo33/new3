<?php
declare(strict_types=1);

namespace App\Twig\Components;

use App\Http\CartCookieFactory;
use App\Repository\CartRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('CartCounter')]
final class CartCounter
{
    public int $count = 0;

    public function __construct(
        private CartRepository $carts,
        private RequestStack $requestStack,
        private CartCookieFactory $cookieFactory,
    ) {}

    public function mount(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            $this->count = 0;
            return;
        }

        $tokenCookieName = $this->cookieFactory->getCookieName();
        $token = (string) ($request->cookies->get($tokenCookieName) ?? '');
        $legacy = (string) ($request->cookies->get('cart_id') ?? '');

        $cart = null;

        if ($token !== '') {
            $cart = $this->carts->findActiveByToken($token);
        }

        if (!$cart && $legacy !== '') {
            try {
                $cartId = Ulid::fromString($legacy);
                $cart = $this->carts->findActiveById($cartId);
            } catch (\InvalidArgumentException) {
                // ignore invalid legacy cookie
            }
        }

        $this->count = $cart ? $cart->getTotalItemQuantity() : 0;
    }
}


