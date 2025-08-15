<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\DeliveryContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class DeliveryContextSubscriber implements EventSubscriberInterface
{
    private const API_PATH_PREFIX = '/api';
	public function __construct(private DeliveryContext $ctx) {}

	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::REQUEST => ['onRequest', 20],
			KernelEvents::RESPONSE => ['onResponse', 0],
		];
	}

	public function onRequest(RequestEvent $event): void
	{
		if (!$event->isMainRequest()) return;
		$request = $event->getRequest();
		// Не использовать сессию на API-запросах (API Platform по умолчанию stateless)
		if (str_starts_with($request->getPathInfo(), self::API_PATH_PREFIX)) return;
		$this->ctx->ensureCity();
	}

	public function onResponse(ResponseEvent $event): void
	{
		if (!$event->isMainRequest()) return;
		$request = $event->getRequest();
		if (str_starts_with($request->getPathInfo(), self::API_PATH_PREFIX)) return;
		$city = $request->attributes->get('_delivery_city_cookie');
		if ($city) {
			$event->getResponse()->headers->setCookie(
				\Symfony\Component\HttpFoundation\Cookie::create(DeliveryContext::CITY_COOKIE_NAME)
					->withValue((string) $city)
					->withPath('/')
					->withExpires(strtotime('+30 days'))
			);
		}
	}
}


