<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;

final class DeliveryContext
{
	private const SESSION_KEY = 'delivery';

	public function __construct(private RequestStack $rs, private GeoIpService $geo) {}

	public function get(): array
	{
		$session = $this->rs->getSession();
		return $session->get(self::SESSION_KEY, []);
	}

	public function ensureCity(): array
	{
		$session = $this->rs->getSession();
		$ctx = $session->get(self::SESSION_KEY, []);
		if (!isset($ctx['cityName'])) {
			$ip = $this->rs->getCurrentRequest()?->getClientIp() ?? '127.0.0.1';
			$guess = $this->geo->guessCity($ip);
			$ctx = array_merge($ctx, $guess);
			$session->set(self::SESSION_KEY, $ctx);
			$response = $this->rs->getCurrentRequest()?->attributes->get('_response');
			if ($response) {
				$response->headers->setCookie(Cookie::create('city')->withValue($ctx['cityName'])->withPath('/')->withExpires(strtotime('+30 days')));
			}
		}
		return $ctx;
	}

	public function setCity(string $name, ?int $id = null): void
	{
		$session = $this->rs->getSession();
		$ctx = $session->get(self::SESSION_KEY, []);
		$ctx['cityName'] = $name;
		if ($id) $ctx['cityId'] = $id;
		unset($ctx['methodCode'], $ctx['pickupPointId']);
		$session->set(self::SESSION_KEY, $ctx);
	}

	public function setMethod(string $code, ?array $extra = null): void
	{
		$session = $this->rs->getSession();
		$ctx = $session->get(self::SESSION_KEY, []);
		$ctx['methodCode'] = $code;
		if ($extra) $ctx = array_merge($ctx, $extra);
		$session->set(self::SESSION_KEY, $ctx);
	}

	public function syncToCart(Cart $cart): void
	{
		$ctx = $this->get();
		if (isset($ctx['cityName'])) $cart->setShipToCity($ctx['cityName']);
		if (isset($ctx['methodCode'])) $cart->setShippingMethod($ctx['methodCode']);
		$data = $cart->getShippingData() ?? [];
		foreach (['pickupPointId','address','zip','etaDays'] as $k) {
			if (isset($ctx[$k])) $data[$k] = $ctx[$k];
		}
		$cart->setShippingData($data);
	}
}


