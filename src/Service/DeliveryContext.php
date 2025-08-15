<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use Symfony\Component\HttpFoundation\RequestStack;

final class DeliveryContext
{
	public const CITY_COOKIE_NAME = 'city';
	private const DELIVERY_SESSION_KEY = 'delivery';

	public function __construct(private RequestStack $rs, private GeoIpService $geo) {}

	/**
	 * Возвращает контекст доставки из сессии.
	 *
	 * @return array{
	 *     cityName?: string,
	 *     cityId?: int,
	 *     methodCode?: string,
	 *     pickupPointId?: int|string,
	 *     address?: string,
	 *     zip?: string,
	 *     etaDays?: int
	 * }
	 */
	public function get(): array
	{
		$session = $this->rs->getSession();
		if ($session === null) return [];
		return $session->get(self::DELIVERY_SESSION_KEY, []);
	}

	/**
	 * Гарантирует наличие города в контексте доставки.
	 * Если города нет, пытается определить по IP, сохраняет в сессии и помечает установку cookie на RESPONSE.
	 *
	 * @return array{
	 *     cityName?: string,
	 *     cityId?: int,
	 *     methodCode?: string,
	 *     pickupPointId?: int|string,
	 *     address?: string,
	 *     zip?: string,
	 *     etaDays?: int
	 * }
	 */
	public function ensureCity(): array
	{
		$session = $this->rs->getSession();
		if ($session === null) return [];
		$ctx = $session->get(self::DELIVERY_SESSION_KEY, []);
		if (!isset($ctx['cityName'])) {
			$ip = $this->rs->getCurrentRequest()?->getClientIp() ?? '127.0.0.1';
			$guess = $this->geo->guessCity($ip);
			$ctx = array_merge($ctx, $guess);
			$session->set(self::DELIVERY_SESSION_KEY, $ctx);
			// Пометим на текущем запросе, что cookie нужно установить на этапе RESPONSE
			$this->rs->getCurrentRequest()?->attributes->set('_delivery_city_cookie', $ctx['cityName']);
		}
		return $ctx;
	}

	public function setCity(string $name, ?int $id = null): void
	{
		$session = $this->rs->getSession();
		if ($session === null) return;
		$ctx = $session->get(self::DELIVERY_SESSION_KEY, []);
		$ctx['cityName'] = $name;
		if ($id) $ctx['cityId'] = $id;
		unset($ctx['methodCode'], $ctx['pickupPointId']);
		$session->set(self::DELIVERY_SESSION_KEY, $ctx);
		// Обновить cookie на этапе RESPONSE
		$this->rs->getCurrentRequest()?->attributes->set('_delivery_city_cookie', $name);
	}

	public function setMethod(string $code, ?array $extra = null): void
	{
		$session = $this->rs->getSession();
		if ($session === null) return;
		$ctx = $session->get(self::DELIVERY_SESSION_KEY, []);
		$ctx['methodCode'] = $code;
		if ($extra) $ctx = array_merge($ctx, $extra);
		$session->set(self::DELIVERY_SESSION_KEY, $ctx);
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


