<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Service\CartSessionStorage;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartSessionCleanupListener implements EventSubscriberInterface
{
    public function __construct(
        private CartSessionStorage $sessionStorage
    ) {}
    
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }
    
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user && method_exists($user, 'getId')) {
            // Миграция произойдет в CartManager::getOrCreateCurrent
            // Здесь просто помечаем для очистки
            $event->getRequest()->attributes->set('_clear_guest_cart', true);
        }
    }
}
