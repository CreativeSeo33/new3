<?php
declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
final class CartCookieListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $cookies = $request->attributes->get('_cart_cookies', []);
        if (empty($cookies)) {
            return;
        }

        foreach ($cookies as $name => $options) {
            $value = $options['value'];
            unset($options['value']);

            $cookie = new Cookie($name, $value, ...$options);
            $response->headers->setCookie($cookie);
        }
    }
}
