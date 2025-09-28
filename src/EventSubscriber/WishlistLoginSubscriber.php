<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\WishlistCookieFactory;
use App\Repository\WishlistRepository;
use App\Service\WishlistManager;
use App\Entity\User as AppUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class WishlistLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WishlistRepository $wishlists,
        private WishlistManager $manager,
        private EntityManagerInterface $em,
        private WishlistCookieFactory $cookieFactory,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof AppUser) return;

        $request = $event->getRequest();
        $token = $request->cookies->get($this->cookieFactory->getCookieName());

        $guest = $token ? $this->wishlists->findByToken($token) : null;
        $userW = $this->wishlists->findByUser($user);

        $final = null;
        if ($guest && $userW) {
            $final = $this->manager->merge($userW, $guest);
            $this->em->remove($guest);
        } elseif ($guest) {
            $guest->setUser($user);
            $guest->setToken(null);
            $final = $guest;
        }

        if ($final) {
            $cookie = $this->cookieFactory->build($request, $final->ensureToken());
            $response = $event->getResponse();
            if ($response) {
                $response->headers->setCookie($cookie);
            }
            $this->em->flush();
        }
    }
}


