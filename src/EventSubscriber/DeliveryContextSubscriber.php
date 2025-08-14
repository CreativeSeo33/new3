<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\DeliveryContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class DeliveryContextSubscriber implements EventSubscriberInterface
{
	public function __construct(private DeliveryContext $ctx) {}

	public static function getSubscribedEvents(): array
	{
		return [KernelEvents::REQUEST => ['onRequest', 20]];
	}

	public function onRequest(RequestEvent $event): void
	{
		if (!$event->isMainRequest()) return;
		$request = $event->getRequest();
		// Не использовать сессию на API-запросах (API Platform по умолчанию stateless)
		if (str_starts_with($request->getPathInfo(), '/api')) return;
		$this->ctx->ensureCity();
	}
}


