<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\CartRepository;
use App\Service\CartManager;
use App\Entity\User as AppUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class CartLoginSubscriber implements EventSubscriberInterface
{
	public function __construct(
		private RequestStack $requestStack,
		private CartRepository $carts,
		private CartManager $manager,
	) {}

	public static function getSubscribedEvents(): array
	{
		return [LoginSuccessEvent::class => 'onLogin'];
	}

	public function onLogin(LoginSuccessEvent $event): void
	{
		$user = $event->getUser();
		if (!$user instanceof AppUser) return;
		$userId = $user->getId();

		$request = $this->requestStack->getCurrentRequest();
		$token = $request?->cookies->get('cart_token');

		$userCart = $this->carts->findActiveByUser($userId);
		$guestCart = $token ? $this->carts->findActiveByToken($token) : null;

		if ($guestCart && $userCart && $guestCart->getId() !== $userCart->getId()) {
			$this->manager->merge($userCart, $guestCart);
		} elseif ($guestCart && !$userCart) {
			$this->manager->assignToUser($guestCart, $userId);
		}
	}
}


