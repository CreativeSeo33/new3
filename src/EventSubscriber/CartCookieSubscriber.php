<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CartCookieSubscriber implements EventSubscriberInterface
{
	public static function getSubscribedEvents(): array
	{
		return [KernelEvents::RESPONSE => 'onResponse'];
	}

	public function onResponse(ResponseEvent $event): void
	{
		$request = $event->getRequest();
		$token = $request->attributes->get('_set_cart_cookie');
		if (!$token) return;

		$response = $event->getResponse();
		$cookie = Cookie::create('cart_token')
			->withValue($token)
			->withPath('/')
			->withHttpOnly(true)
			->withSecure($request->isSecure())
			->withExpires(strtotime('+30 days'));
		$response->headers->setCookie($cookie);
	}
}


